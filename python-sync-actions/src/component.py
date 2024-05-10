"""
Template Component main class.

"""
import json
import logging
from io import StringIO

from keboola.component.base import ComponentBase, sync_action
from keboola.component.exceptions import UserException
from nested_lookup import nested_lookup

import configuration
from configuration import Configuration, DataPath
from actions.curl import build_job_from_curl
from actions.mapping import infer_mapping
from http_generic.auth import AuthMethodBuilder, AuthBuilderError
from http_generic.client import GenericHttpClient
from user_functions import UserFunctions

from placeholders_utils import PlaceholdersUtils

# configuration variables
KEY_API_TOKEN = '#api_token'
KEY_PRINT_HELLO = 'print_hello'

# list of mandatory parameters => if some is missing,
# component will fail with readable message on initialization.
REQUIRED_PARAMETERS = [KEY_PRINT_HELLO]
REQUIRED_IMAGE_PARS = []


class Component(ComponentBase):
    """
        Extends base class for general Python components. Initializes the CommonInterface
        and performs configuration validation.

        For easier debugging the data folder is picked up by default from `../data` path,
        relative to working directory.

        If `debug` parameter is present in the `config.json`, the default logger is set to verbose DEBUG mode.
    """

    def __init__(self):
        super().__init__()
        self.log = StringIO()
        logging.getLogger().addHandler(logging.StreamHandler(self.log))

        self.user_functions = UserFunctions()

        self._configuration: Configuration = None
        self._client: GenericHttpClient = None
        self._parent_params = {}
        self._final_results = []
        self._final_response = None

    def run(self):
        """
        Main component method
        """
        pass

    def init_component(self):

        self._configuration = configuration.convert_to_v2(self.configuration.parameters)

        # build authentication method
        auth_method = None
        authentication = self._configuration.api.authentication
        try:
            if authentication:
                # evaluate user_params inside the user params itself
                user_params = self._configuration.user_parameters
                user_params = self._fill_in_user_parameters(user_params, user_params)
                # apply user parameters
                auth_method_params = self._fill_in_user_parameters(authentication.parameters, user_params)
                auth_method = AuthMethodBuilder.build(authentication.type, **auth_method_params)
        except AuthBuilderError as e:
            raise UserException(e) from e

        # init client
        self._client = GenericHttpClient(base_url=self._configuration.api.base_url,
                                         max_retries=self._configuration.api.retry_config.max_retries,
                                         backoff_factor=self._configuration.api.retry_config.backoff_factor,
                                         status_forcelist=self._configuration.api.retry_config.codes,
                                         auth_method=auth_method
                                         )

    def _fill_in_user_parameters(self, conf_objects: dict, user_param: dict):
        """
        This method replaces user parameter references via attr + parses functions inside user parameters,
        evaluates them and fills in the resulting values

        Args:
            conf_objects: Configuration that contains the references via {"attr": "key"} to user parameters or function
                            definitions
            user_param: User parameters that are used to fill in the values

        Returns:

        """
        # convert to string minified
        steps_string = json.dumps(conf_objects, separators=(',', ':'))
        # dirty and ugly replace
        for key in user_param:
            if isinstance(user_param[key], dict):
                # in case the parameter is function, validate, execute and replace value with result
                user_param[key] = self._perform_custom_function(key, user_param[key], user_param)

            lookup_str = '{"attr":"' + key + '"}'
            steps_string = steps_string.replace(lookup_str, '"' + str(user_param[key]) + '"')
        new_steps = json.loads(steps_string)
        non_matched = nested_lookup('attr', new_steps)

        if non_matched:
            raise ValueError(
                'Some user attributes [{}] specified in parameters '
                'are not present in "user_parameters" field.'.format(non_matched))
        return new_steps

    def _fill_placeholders(self, placeholders, path):
        """
        Fill placeholders in the path
        Args:
            placeholders: placeholders - names dict to fill
            path: path with placeholders
            row: row with data
        """

        result_path = path
        for key, dict in placeholders[0].items():
            result_path = result_path.replace(f"{{{dict.get('placeholder')}}}", str(dict.get('value')))
        return result_path

    def _process_nested_job(self, parent_result, config, parent_results_list, client,
                            method, **request_parameters) -> list:
        """
        Process nested job
        Args:
            parent_result: result of the parent job
            config: configuration of the nested job
            parent_results_list: list of parent results
            client: http client
            method: method to use
            request_parameters: request parameters
        """

        for row in parent_result:

            parent_results_ext = parent_results_list + [row]

            placeholders = PlaceholdersUtils.get_params_for_child_jobs(config.get('placeholders', {}),
                                                                       parent_results_ext, self._parent_params)

            self._parent_params = placeholders[0]
            row_path = self._fill_placeholders(placeholders, config['endpoint'])
            response = client.send_request(method=method, endpoint_path=row_path, **request_parameters)
            child_response = self._parse_data(response.json(), DataPath(config.get('dataType'),
                                                                        config.get('dataField', '.')))
            children = config.get('children', [])
            results = []
            if children[0] if children else None:
                nested_data = self._process_nested_job(child_response, children[0], parent_results_ext,
                                                       client, method, **request_parameters)
                results.append(nested_data)
            else:
                self._final_results.append(child_response)
                self._final_response = response

        return results

    def _perform_custom_function(self, key: str, function_cfg: dict, user_params: dict):
        """
        Perform custom function recursively (may be nested)
        Args:
            key: key of the user parameter wher the function is
            function_cfg: conf of the function
            user_params:

        Returns:

        """
        if function_cfg.get('attr'):
            return user_params[function_cfg['attr']]
        if not function_cfg.get('function'):
            raise ValueError(
                F'The user parameter {key} value is object and is not a valid function object: {function_cfg}')
        new_args = []
        if function_cfg.get('args'):
            for arg in function_cfg.get('args'):
                if isinstance(arg, dict):
                    arg = self._perform_custom_function(key, arg, user_params)
                new_args.append(arg)
            function_cfg['args'] = new_args

        return self.user_functions.execute_function(function_cfg['function'], *function_cfg.get('args', []))

    def _parse_data(self, data, path) -> list:
        """
        Parse data from the response
        Args:
            data: data to parse
            path: path to the data

        Returns:

        """

        if path.path is None:
            result = data
        else:
            if path.delimiter in path.path:
                keys = path.path.split(path.delimiter)  # Split the path only if delimiter is present
            else:
                keys = [path.path]

            value = data
            try:
                for key in keys:
                    value = value[key]
                result = value
            except KeyError:
                result = None

        if not isinstance(result, list):
            result = [result] if result is not None else []

        return result

    def make_call(self) -> list:
        """
        Make call to the API
        Returns:
            list: [status_code, response, results, log]
        """
        self.init_component()
        self._client.login()

        api_cfg = self._configuration.api
        request_cfg = self._configuration.request_parameters
        # fix KBC bug
        user_params = self._configuration.user_parameters
        # evaluate user_params inside the user params itself
        user_params = self._fill_in_user_parameters(user_params, user_params)

        # build headers
        headers = {**api_cfg.default_headers.copy(), **request_cfg.headers.copy()}
        new_headers = self._fill_in_user_parameters(headers, user_params)

        # build additional parameters
        query_parameters = {**api_cfg.default_query_parameters.copy(), **request_cfg.query_parameters.copy()}
        query_parameters = self._fill_in_user_parameters(query_parameters, user_params)
        ssl_verify = api_cfg.ssl_verification
        timeout = api_cfg.timeout
        # additional_params = self._build_request_parameters(additional_params_cfg)
        request_parameters = {'params': query_parameters,
                              'headers': new_headers,
                              'verify': ssl_verify,
                              'timeout': timeout}

        endpoint_path = request_cfg.endpoint_path

        # use client to send requests / perform actions
        self._final_response = self._client.send_request(method=request_cfg.method, endpoint_path=endpoint_path,
                                                         **request_parameters)

        headers = dict(self._final_response.headers)

        result = self._parse_data(self._final_response.json(), self._configuration.data_path)

        if request_cfg.nested_job:
            parent_results = []
            self._process_nested_job(result, request_cfg.nested_job[0], parent_results, self._client,
                                     request_cfg.method, **request_parameters)
        else:
            self._final_results = result

        return [self._final_response.status_code, self._final_response,
                self._final_results, self.log.getvalue()]

    @sync_action('load_from_curl')
    def load_from_curl(self) -> dict:
        """
        Load configuration from cURL command
        """
        self.init_component()
        curl_command = self.configuration.parameters.get('_curl_command')
        if not curl_command:
            raise ValueError('cURL command not provided')
        job = build_job_from_curl(curl_command, self._configuration.api.base_url)

        return job.to_dict()

    @sync_action('infer_mapping')
    def infer_mapping(self) -> dict:
        """
        Load configuration from cURL command
        """
        self.init_component()
        # TODO: get sample data
        data = [{
            "id": "236606822",
            "name": "My new configuration",
            "description": "",
            "created": "2017-02-14T14:38:26+0100",
            "creatorToken": {
                "id": 27978,
                "description": "ondrej.popelka@keboola.com"
            },
            "version": 1,
            "changeDescription": "Initial configuration",
            "isDeleted": False,
            "configuration": {
                "key": "value"
            },
            "rowsSortOrder": [],
            "rows": [],
            "state": {},
            "currentVersion": {
            }
        }, {
            "id": "236606822",
            "name": "My new configuration",
            "description": "",
            "created": "2017-02-14T14:38:26+0100",
            "creatorToken": {
                "id": 27978,
                "description": "ondrej.popelka@keboola.com"
            },
            "version": 1,
            "changeDescription": "Initial configuration",
            "isDeleted": False,
            "configuration": {
                "key": "value",
                "key2": "value2"
            },
            "rowsSortOrder": [],
            "rows": [],
            "state": {},
            "currentVersion": {
                "created": "2017-02-14T14:38:26+0100",
                "creatorToken": {
                    "id": 27978,
                    "description": "ondrej.popelka@keboola.com"
                },
                "changeDescription": "Initial configuration",
                "versionIdentifier": "01H2W8N0ZJJB6V126C8ZSND28N"
            }
        }]
        mapping = infer_mapping(data)
        return mapping

    @sync_action('test_request')
    def test_request(self):

        self.make_call()

        return [self._final_response.status_code, self._final_response.json(),
                self._final_results, self.log.getvalue()]


"""
        Main entrypoint
"""
if __name__ == "__main__":
    try:
        comp = Component()
        # this triggers the run method by default and is controlled by the configuration.action parameter
        comp.execute_action()
    except UserException as exc:
        logging.exception(exc)
        exit(1)
    except Exception as exc:
        logging.exception(exc)
        exit(2)
