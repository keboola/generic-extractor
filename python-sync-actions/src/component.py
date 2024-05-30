"""
Template Component main class.

"""
import copy
import logging
from io import StringIO
from json import JSONDecodeError
from typing import List

import requests
from keboola.component.base import ComponentBase, sync_action
from keboola.component.exceptions import UserException

import configuration
from actions.curl import build_job_from_curl
from actions.mapping import infer_mapping
from configuration import Configuration, ConfigHelpers
from http_generic.auth import AuthMethodBuilder, AuthBuilderError
from http_generic.client import GenericHttpClient, HttpClientError
from placeholders_utils import PlaceholdersUtils

MAX_CHILD_CALLS = 20

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

        # remove default handler
        for h in logging.getLogger().handlers:
            logging.getLogger().removeHandler(h)

        logging.getLogger().addHandler(logging.StreamHandler(self.log))

        # always set debug mode
        self.set_debug_mode()

        logging.info("Component initialized")

        self._configurations: List[Configuration] = None
        self._configuration: Configuration = None
        self._client: GenericHttpClient = None
        self._parent_params = {}
        self._final_results = []
        self._parent_results = []
        self._final_response: requests.Response = None
        self._conf_helpers = ConfigHelpers()

    def run(self):
        """
        Main component method
        """
        # self.make_call()
        self.test_request()

    def init_component(self):

        self._configurations = configuration.convert_to_v2(self.configuration.parameters)

        self._configuration = self._configurations[0]

        # build authentication method
        auth_method = None
        authentication = self._configuration.api.authentication
        try:
            if authentication:
                # evaluate user_params inside the user params itself
                user_params = self._configuration.user_parameters
                user_params = self._conf_helpers.fill_in_user_parameters(user_params, user_params)
                # apply user parameters
                auth_method_params = self._conf_helpers.fill_in_user_parameters(authentication.parameters, user_params,
                                                                                False)
                auth_method = AuthMethodBuilder.build(authentication.type, **auth_method_params)
        except AuthBuilderError as e:
            raise UserException(e) from e

        # evaluate user_params inside the user params itself
        self._configuration.user_parameters = self._conf_helpers.fill_in_user_parameters(
            self._configuration.user_parameters,
            self._configuration.user_parameters)

        self._configuration.user_data = self._conf_helpers.fill_in_user_parameters(self._configuration.user_data,
                                                                                   self._configuration.user_parameters)

        # init client
        self._client = GenericHttpClient(base_url=self._configuration.api.base_url,
                                         max_retries=self._configuration.api.retry_config.max_retries,
                                         backoff_factor=self._configuration.api.retry_config.backoff_factor,
                                         status_forcelist=self._configuration.api.retry_config.codes,
                                         auth_method=auth_method
                                         )

    def _get_values_to_hide(self) -> list[str]:
        """
        Get values to hide
        Args:
        """
        user_params = self._configuration.user_parameters
        secrets = [value for key, value in user_params.items() if key.startswith('#') or key.startswith('__')]

        # get secrets from the auth method
        if self._client._auth_method:  # noqa
            auth_secrets = self._client._auth_method.get_secrets()  # noqa
            secrets.extend(auth_secrets)
        return secrets

    def _replace_words(self, obj, words, replacement="--HIDDEN--"):
        # Helper function to perform replacement in strings
        def replace_in_string(s):
            for word in words:
                s = s.replace(word, replacement)
            return s

        # If the object is a dictionary
        if isinstance(obj, dict):
            new_obj = {}
            for key, value in obj.items():
                new_key = replace_in_string(key) if isinstance(key, str) else key
                new_value = self._replace_words(value, words, replacement)
                new_obj[new_key] = new_value
            return new_obj

        # If the object is a list
        elif isinstance(obj, list):
            return [self._replace_words(item, words, replacement) for item in obj]

        # If the object is a tuple
        elif isinstance(obj, tuple):
            return tuple(self._replace_words(item, words, replacement) for item in obj)

        # If the object is a set
        elif isinstance(obj, set):
            return {self._replace_words(item, words, replacement) for item in obj}

        # If the object is a custom object
        elif hasattr(obj, "__dict__"):
            new_obj = copy.deepcopy(obj)
            for attr in vars(new_obj):
                setattr(new_obj, attr, self._replace_words(getattr(new_obj, attr), words, replacement))
            return new_obj

        # If the object is a string and contains any of the words
        elif isinstance(obj, str):
            return replace_in_string(obj)

        # Return the object if it is of any other type
        else:
            return obj

    # Function to deep copy the object and replace specified words with a replacement string
    def _deep_copy_and_replace_words(self, original_obj, words):
        copied_obj = copy.deepcopy(original_obj)
        return self._replace_words(copied_obj, words)

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

    # def _process_nested_job(self, parent_result, config, parent_results_list, client,
    #                         method, **request_parameters) -> list:
    #     """
    #     Process nested job
    #     Args:
    #         parent_result: result of the parent job
    #         config: configuration of the nested job
    #         parent_results_list: list of parent results
    #         client: http client
    #         method: method to use
    #         request_parameters: request parameters
    #     """
    #     results = []
    #     for row in parent_result or [{}]:
    #
    #         parent_results_ext = parent_results_list + [row]
    #
    #         placeholders = PlaceholdersUtils.get_params_for_child_jobs(config.get('placeholders', {}),
    #                                                                    parent_results_ext, self._parent_params)
    #
    #         self._parent_params = placeholders[0]
    #         row_path = self._fill_placeholders(placeholders, config['endpoint'])
    #         response = client.send_request(method=method, endpoint_path=row_path, **request_parameters)
    #         child_response = self._parse_data(response.json(), DataPath(config.get('dataType'),
    #                                                                     config.get('dataField', '.')))
    #         children = config.get('children', [])
    #         results = []
    #         if children[0] if children else None:
    #             nested_data = self._process_nested_job(child_response, children[0], parent_results_ext,
    #                                                    client, method, **request_parameters)
    #             results.append(nested_data)
    #         else:
    #             self._final_results.append(child_response)
    #             self._final_response = response
    #
    #     return results

    def _parse_data(self, data, path) -> list:
        """
        Parse data from the response
        Args:
            data: data to parse
            path: path to the data

        Returns:

        """

        if path.path == '.':
            result = data
        else:
            keys = path.path.split(path.delimiter)
            value = data.copy()
            try:
                for key in keys:
                    value = value[key]
                result = value
            except KeyError:
                return [f"Path {path.path} not found in the response data"]

        # TODO: check if the result is list
        if not isinstance(result, list):
            element_name = 'root' if path.path == '.' else path.path
            return [f"The {element_name} element of the response is not a list, "
                    f"please change your Data Selector path to list"]

        return result

    def make_call(self) -> tuple[list, any, str, str]:
        """
        Make call to the API
        Returns:
            requests.Response
        """
        self.init_component()
        if not self._configuration.request_parameters:
            raise ValueError("__SELECTED_JOB is missing!")
        self._client.login()
        # set back to debug because sync action mutes it
        logging.getLogger().setLevel(logging.DEBUG)

        final_results = []

        self._parent_results = [{}] * len(self._configurations)

        def recursive_call(parent_result, config_index=0):

            if parent_result:
                self._parent_results[config_index - 1] = parent_result

            if config_index >= len(self._configurations):
                return final_results, self._final_response.json(), self.log.getvalue()

            job = self._configurations[config_index]

            api_cfg = job.api
            request_cfg = job.request_parameters
            # fix KBC bug
            user_params = job.user_parameters
            # evaluate user_params inside the user params itself
            user_params = self._conf_helpers.fill_in_user_parameters(user_params, user_params)

            # build headers
            headers = {**api_cfg.default_headers.copy(), **request_cfg.headers.copy()}
            new_headers = self._conf_helpers.fill_in_user_parameters(headers, user_params)

            # build additional parameters
            query_parameters = {**api_cfg.default_query_parameters.copy(), **request_cfg.query_parameters.copy()}
            query_parameters = self._conf_helpers.fill_in_user_parameters(query_parameters, user_params)
            ssl_verify = api_cfg.ssl_verification
            timeout = api_cfg.timeout
            # additional_params = self._build_request_parameters(additional_params_cfg)
            request_parameters = {'params': query_parameters,
                                  'headers': new_headers,
                                  'verify': ssl_verify,
                                  'timeout': timeout}

            if job.request_content.content_type == configuration.ContentType.json:
                request_parameters['json'] = job.request_content.body
            elif job.request_content.content_type == configuration.ContentType.form:
                request_parameters['data'] = job.request_content.body

            row_path = job.request_parameters.endpoint_path

            if job.request_parameters.placeholders:
                placeholders = PlaceholdersUtils.get_params_for_child_jobs(job.request_parameters.placeholders,
                                                                           self._parent_results, self._parent_params)
                self._parent_params = placeholders[0]
                row_path = self._fill_placeholders(placeholders, job.request_parameters.endpoint_path)

            self._final_response = self._client.send_request(method=job.request_parameters.method,
                                                             endpoint_path=row_path, **request_parameters)

            current_results = self._parse_data(self._final_response.json(), job.data_path)

            if config_index == len(self._configurations) - 1:
                final_results.extend(current_results)
            else:
                if isinstance(current_results, list):
                    # limit the number of calls to 10 because of timeout
                    for result in current_results[:MAX_CHILD_CALLS]:
                        recursive_call(result, config_index + 1)
                else:
                    recursive_call(current_results, config_index + 1)

        try:
            recursive_call({})
            error_message = ''
        except HttpClientError as e:
            error_message = str(e)
            if e.response is not None:
                self._final_response = e.response
            else:
                raise UserException(e) from e

        return final_results, self._final_response, self.log.getvalue(), error_message

    @sync_action('load_from_curl')
    def load_from_curl(self) -> dict:
        """
        Load configuration from cURL command
        """
        self.init_component()
        curl_command = self.configuration.parameters.get('__CURL_COMMAND')
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
        data, response, log, error = self.make_call()

        if error:
            raise UserException(error)

        nesting_level = self.configuration.parameters.get('__NESTING_LEVEL', 2)
        primary_keys = self.configuration.parameters.get('__PRIMARY_KEY', [])
        is_child_job = len(self.configuration.parameters.get('__SELECTED_JOB', '').split('_')) > 1
        parent_pkey = []
        if len(self._configurations) > 1:
            parent_pkey = [f'parent_{p}' for p in self._configurations[-1].request_parameters.placeholders.keys()]

        if not data:
            raise UserException("The request returned no data to infer mapping from.")

        user_data_columns = []
        if self._configuration.user_data and not is_child_job:
            for record in data:
                for key, value in self._configuration.user_data.items():
                    user_data_columns.append(key)
                    if key in record:
                        raise UserException(f"User data key [{key}] already exists in the response data, "
                                            f"please change the name.")
                    record[key] = value

        mapping = infer_mapping(data, primary_keys, parent_pkey,
                                user_data_columns=user_data_columns,
                                max_level_nest_level=nesting_level)
        return mapping

    @sync_action('perform_function')
    def perform_function_sync(self) -> dict:
        self.init_component()
        function_cfg = self.configuration.parameters['__FUNCTION_CFG']
        return {"result": ConfigHelpers().perform_custom_function('function',
                                                                  function_cfg, self._configuration.user_parameters)}

    @sync_action('test_request')
    def test_request(self):
        results, response, log, error_message = self.make_call()

        body = None
        if response.request.body:
            body = response.request.body.decode('utf-8')

        secrets_to_hide = self._get_values_to_hide()
        filtered_response = self._deep_copy_and_replace_words(self._final_response, secrets_to_hide)
        filtered_log = self._deep_copy_and_replace_words(self.log.getvalue(), secrets_to_hide)
        filtered_body = self._deep_copy_and_replace_words(body, secrets_to_hide)

        # get response data:
        try:
            response_data = filtered_response.json()
        except JSONDecodeError:
            response_data = filtered_response.text

        result = {
            "response": {
                "status_code": filtered_response.status_code,
                "reason": filtered_response.reason,
                "data": response_data,
                "headers": dict(filtered_response.headers)
            },
            "request": {
                "url": response.request.url,
                "method": response.request.method,
                "data": filtered_body,
                "headers": dict(filtered_response.request.headers)
            },
            "records": results,
            "debug_log": filtered_log
        }
        return result


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
