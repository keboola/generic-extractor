import dataclasses
import json
import time
import urllib.parse as urlparse
from dataclasses import dataclass, field
from enum import Enum
from typing import List, Tuple, Optional, Literal

import dataconf
from nested_lookup import nested_lookup

from user_functions import UserFunctions


class ConfigurationBase:
    @staticmethod
    def _convert_private_value(value: str):
        return value.replace('"#', '"pswd_')

    @staticmethod
    def _convert_private_value_inv(value: str):
        if value and value.startswith("pswd_"):
            return value.replace("pswd_", "#", 1)
        else:
            return value

    @classmethod
    def load_from_dict(cls, configuration: dict):
        """
        Initialize the configuration dataclass object from dictionary.
        Args:
            configuration: Dictionary loaded from json configuration.

        Returns:

        """
        json_conf = json.dumps(configuration)
        json_conf = ConfigurationBase._convert_private_value(json_conf)
        return dataconf.loads(json_conf, cls, ignore_unexpected=True)

    @classmethod
    def get_dataclass_required_parameters(cls) -> List[str]:
        """
        Return list of required parameters based on the dataclass definition (no default value)
        Returns: List[str]

        """
        return [
            cls._convert_private_value_inv(f.name)
            for f in dataclasses.fields(cls)
            if f.default == dataclasses.MISSING and f.default_factory == dataclasses.MISSING
        ]


@dataclass
class RetryConfig(ConfigurationBase):
    max_retries: int = 1
    backoff_factor: float = 0.3
    codes: Tuple[int, ...] = (500, 502, 504)


@dataclass
class Authentication(ConfigurationBase):
    type: str
    parameters: dict = field(default_factory=dict)


@dataclass
class Pagination(ConfigurationBase):
    type: str
    parameters: dict = field(default_factory=dict)


@dataclass
class ApiConfig(ConfigurationBase):
    base_url: str
    default_query_parameters: dict = field(default_factory=dict)
    default_headers: dict = field(default_factory=dict)
    pagination: dict = field(default_factory=dict)
    authentication: Authentication = None
    retry_config: RetryConfig = field(default_factory=RetryConfig)
    timeout: float | None = None
    ssl_verify: bool = True  # toggles requests.[method](verify=True/False)
    ca_cert: str = ""  # if provided, this value will be written to a temp file and used instead of ssl_verify
    client_cert_key: str = ""  # client certificate bundled with private key (will also be written to a temp file)
    jobs: list[dict] = field(default_factory=list)


@dataclass
class ApiRequest(ConfigurationBase):
    method: str
    endpoint_path: str
    placeholders: dict = field(default_factory=dict)
    headers: dict = field(default_factory=dict)
    query_parameters: dict = field(default_factory=dict)
    continue_on_failure: bool = False
    nested_job: dict = field(default_factory=dict)
    scroller: str = None
    pagination: Pagination = None


@dataclass
class DataPath(ConfigurationBase):
    path: str = "."
    delimiter: str = "."

    def to_dict(self):
        return {"path": self.path, "delimiter": self.delimiter}


# CONFIGURATION OBJECT
class ContentType(str, Enum):
    none = "none"
    json = "json"
    form = "form"


@dataclass
class RequestContent(ConfigurationBase):
    content_type: ContentType
    query_parameters: dict = field(default_factory=dict)
    body: Optional[dict] = None


@dataclass
class Configuration(ConfigurationBase):
    api: ApiConfig
    request_parameters: ApiRequest
    request_content: RequestContent
    user_parameters: dict = field(default_factory=dict)
    user_data: dict = field(default_factory=dict)
    data_path: DataPath = field(default_factory=DataPath)


class ConfigurationKeysV2(Enum):
    api = "api"
    user_parameters = "user_parameters"
    request_options = "request_options"

    @classmethod
    def list(cls):
        return list(map(lambda c: c.value, cls))


def _return_ui_params(data) -> list[str]:
    results = []

    def search_dict(d):
        if isinstance(d, dict):
            for key, value in d.items():
                if key == "attr" and isinstance(value, str) and (value.startswith("#__") or value.startswith("__")):
                    results.append(value)
                elif isinstance(value, dict) or isinstance(value, list):
                    search_dict(value)
        elif isinstance(d, list):
            for item in d:
                search_dict(item)

    search_dict(data)
    return results


def _remove_auth_from_dict(dictionary: dict, to_remove: list, auth_method: str) -> dict:
    filtered_dict = {}
    for key, value in dictionary.items():
        if isinstance(value, dict) and auth_method == "bearer":
            if key != "Authorization":
                filtered_value = _remove_auth_from_dict(value, to_remove)
                if filtered_value:
                    filtered_dict[key] = filtered_value
        else:
            if value not in to_remove:
                filtered_dict[key] = value

    return filtered_dict


def convert_to_v2(configuration: dict) -> list[Configuration]:
    """
    Convert configuration to v2 format
    Args:
        configuration: Configuration in v1 format

    Returns: Configuration in v2 format

    """
    user_parameters = build_user_parameters(configuration)

    api_json = configuration.get("api", {})
    base_url = api_json.get("baseUrl", "")
    jobs = configuration.get("config", {}).get("jobs", [])
    default_headers_org = api_json.get("http", {}).get("headers", {})

    default_query_parameters_org = {}
    if http_conf := api_json.get("http"):
        if default_options := http_conf.get("defaultOptions"):
            default_query_parameters_org = default_options.get("params") or {}

    auth_method = configuration.get("config").get("__AUTH_METHOD")

    default_headers = _remove_auth_from_dict(default_headers_org, _return_ui_params(configuration), auth_method)
    default_query_parameters = _remove_auth_from_dict(
        default_query_parameters_org, _return_ui_params(configuration), auth_method
    )

    pagination = {}
    if api_json.get("pagination", {}).get("scrollers"):
        pagination = api_json.get("pagination", {}).get("scrollers")
    elif api_json.get("pagination"):
        pagination["common"] = api_json.get("pagination")

    if ca_cert := api_json.get("caCertificate"):
        ca_cert = ca_cert.strip()
    else:
        ca_cert = ""

    if client_cert_key := api_json.get("#clientCertificate"):
        client_cert_key = client_cert_key.strip()
    else:
        client_cert_key = ""

    api_config = ApiConfig(
        base_url=base_url,
        default_headers=default_headers,
        default_query_parameters=default_query_parameters,
        pagination=pagination,
        ssl_verify=api_json.get("ssl_verify", True),
        ca_cert=ca_cert,
        client_cert_key=client_cert_key,
        jobs=jobs,
    )

    api_config.retry_config = build_retry_config(configuration)
    api_config.authentication = AuthMethodConverter.convert(configuration)

    requests = []

    jobs = build_api_request(configuration)

    for api_request, request_content, data_path in jobs:
        requests.append(
            Configuration(
                api=api_config,
                request_parameters=api_request,
                request_content=request_content,
                user_parameters=user_parameters,
                user_data=configuration.get("config", {}).get("userData", {}),
                data_path=data_path,
            )
        )

    return requests


def build_retry_config(configuration: dict) -> RetryConfig:
    """
    Build retry configuration from configuration
    Args:
        configuration: Configuration in v2 format

    Returns: Retry configuration

    """
    http_section = configuration.get("api", {}).get("http", {})
    return RetryConfig(
        max_retries=http_section.get("maxRetries", 10),
        codes=http_section.get("codes", (500, 502, 503, 504, 408, 420, 429)),
    )


def build_user_parameters(configuration: dict) -> dict:
    """
    Build user parameters from configuration
    Args:
        configuration: Configuration in v2 format

    Returns: User parameters

    """
    config_excluded_keys = [
        "__AUTH_METHOD",
        "__NAME",
        "#__BEARER_TOKEN",
        "jobs",
        "outputBucket",
        "incrementalOutput",
        "http",
        "debug",
        "mappings",
        " #username",
        "#password",
        "userData",
    ]
    user_parameters = {}
    for key, value in configuration.get("config", {}).items():
        if key not in config_excluded_keys:
            user_parameters[key] = value
    return user_parameters


def build_api_request(configuration: dict) -> List[Tuple[ApiRequest, RequestContent, DataPath]]:
    """
    Build API request and content from configuration
    Args:
        configuration: Configuration in v2 format

    Returns: list of tuples (ApiRequest, RequestContent, DataPath)

    """

    result_requests = []

    job_path: str = configuration.get("__SELECTED_JOB")

    if not job_path:
        # job path may be empty for other actions
        return [(None, None, None)]

    selected_jobs = job_path.split("_")

    nested_path = []

    for index in selected_jobs:
        nested_path.append(int(index))

        endpoint_config = configuration.get("config", {}).get("jobs")[nested_path[0]]

        if not endpoint_config:
            raise ValueError("Jobs section not found in the configuration, no endpoint specified")

        for child in nested_path[1:]:
            try:
                endpoint_config = endpoint_config.get("children", [])[child]
            except IndexError:
                raise ValueError("Jobs section not found in the configuration, no endpoint specified")

        method = endpoint_config.get("method", "GET")

        request_content = build_request_content(method, endpoint_config.get("params", {}))
        # use real method
        if method.upper() == "FORM":
            method = "POST"

        endpoint_path = endpoint_config.get("endpoint")

        data_field = endpoint_config.get("dataField")

        placeholders = endpoint_config.get("placeholders", {})

        scroller = endpoint_config.get("scroller", "common")

        if isinstance(data_field, dict):
            path = data_field.get("path")
            delimiter = data_field.get("delimiter", ".")
            data_path = DataPath(path=path, delimiter=delimiter)
        elif data_field is None:
            data_path = None
        else:
            path = data_field or "."
            delimiter = "."
            data_path = DataPath(path=path, delimiter=delimiter)

        # query params are supported only for GET requests
        if request_content.content_type == ContentType.none:
            query_params = endpoint_config.get("params", {})
        else:
            query_params = {}

        result_requests.append(
            (
                ApiRequest(
                    method=method,
                    endpoint_path=endpoint_path,
                    placeholders=placeholders,
                    headers=endpoint_config.get("headers", {}),
                    query_parameters=query_params,
                    scroller=scroller,
                ),
                request_content,
                data_path,
            )
        )

    return result_requests


def build_request_content(method: Literal["GET", "POST", "FORM"], params: dict) -> RequestContent:
    match method:
        case "GET":
            request_content = RequestContent(ContentType.none, query_parameters=params)
        case "POST":
            request_content = RequestContent(ContentType.json, body=params)
        case "FORM":
            request_content = RequestContent(ContentType.form, body=params)
        case _:
            raise ValueError(f"Unsupported method: {method}")
    return request_content


def _find_api_key_location(dictionary):
    position = None
    final_key = None

    for key, val in dictionary.get("defaultOptions", {}).get("params", {}).items():
        if val == {"attr": "#__AUTH_TOKEN"}:
            final_key = key
            position = "query"

    for key, val in dictionary.get("headers", {}).items():
        if val == {"attr": "#__AUTH_TOKEN"}:
            final_key = key
            position = "headers"

    return position, final_key


class AuthMethodConverter:
    SUPPORTED_METHODS = ["basic", "api-key", "bearer"]

    @classmethod
    def convert(cls, config_parameters: dict) -> Authentication | None:
        """

        Args:
            config_parameters (dict):
        """
        auth_method = config_parameters.get("config", {}).get("__AUTH_METHOD", None)
        # or take it form the authentication section
        auth_method = auth_method or config_parameters.get("api", {}).get("authentication", {}).get("type")
        if not auth_method or auth_method == "custom":
            return None

        methods = {
            "basic": cls._convert_basic,
            "bearer": cls._convert_bearer,
            "api-key": cls._convert_api_key,
            "query": cls._convert_query,
            "login": cls._convert_login,
            "oauth2": cls._convert_login,
        }

        func = methods.get(auth_method)

        if func:
            return func(config_parameters)
        else:
            raise ValueError(f"Unsupported auth method: {auth_method}")

    @classmethod
    def _convert_basic(cls, config_parameters: dict) -> Authentication:
        username = config_parameters.get("config").get("username")
        password = config_parameters.get("config").get("#password")
        if not username or not password:
            raise ValueError("Username or password not found in the BasicAuth configuration")

        return Authentication(type="BasicHttp", parameters={"username": username, "#password": password})

    @classmethod
    def _convert_api_key(cls, config_parameters: dict) -> Authentication:
        position, key = _find_api_key_location(config_parameters.get("api").get("http"))
        token = config_parameters.get("config").get("#__AUTH_TOKEN")

        return Authentication(type="ApiKey", parameters={"key": key, "token": token, "position": position})

    @classmethod
    def _convert_query(cls, config_parameters: dict) -> Authentication:
        query_params = config_parameters.get("api").get("authentication").get("query")
        query_params_filled = ConfigHelpers().fill_in_user_parameters(query_params, config_parameters.get("config"))

        return Authentication(type="Query", parameters={"params": query_params_filled})

    @classmethod
    def _convert_bearer(cls, config_parameters: dict) -> Authentication:
        token = config_parameters.get("config").get("#__BEARER_TOKEN")
        if not token:
            raise ValueError("Bearer token not found in the Bearer Token Authentication configuration")

        return Authentication(type="BearerToken", parameters={"#token": token})

    @classmethod
    def _convert_login(cls, config_parameters: dict) -> Authentication:
        method_mapping = {"GET": "GET", "POST": "POST", "FORM": "POST"}
        helpers = ConfigHelpers()
        login_request: dict = config_parameters.get("api", {}).get("authentication", {}).get("loginRequest", {})
        api_request: dict = config_parameters.get("api", {}).get("authentication", {}).get("apiRequest", {})
        # evaluate functions and user parameters
        user_parameters = build_user_parameters(config_parameters)
        user_parameters = helpers.fill_in_user_parameters(user_parameters, user_parameters)
        login_request_eval = helpers.fill_in_user_parameters(login_request, user_parameters)
        # the function evaluation is left for the Auth method because of the response placeholder
        api_request_eval = helpers.fill_in_user_parameters(api_request, user_parameters, False)

        if not login_request:
            raise ValueError("loginRequest configuration not found in the Login 88Authentication configuration")

        login_endpoint: str = login_request_eval.get("endpoint")
        login_url = urlparse.urljoin(config_parameters.get("api", {}).get("baseUrl", ""), login_endpoint)

        method = login_request_eval.get("method", "GET")

        login_request_content: RequestContent = build_request_content(method, login_request_eval.get("params", {}))

        try:
            result_method: str = method_mapping[login_request_eval.get("method", "GET").upper()]
        except KeyError:
            raise ValueError(f"Unsupported method: {login_request_eval.get('method')}")

        login_query_parameters: dict = login_request_content.query_parameters
        login_headers: dict = login_request_eval.get("headers", {})
        api_request_headers: dict = api_request_eval.get("headers", {})
        api_request_query_parameters: dict = api_request_eval.get("params", {})

        parameters = {
            "login_endpoint": login_url,
            "method": result_method,
            "login_query_parameters": login_query_parameters,
            "login_headers": login_headers,
            "login_query_body": login_request_content.body,
            "login_content_type": login_request_content.content_type.value,
            "api_request_headers": api_request_headers,
            "api_request_query_parameters": api_request_query_parameters,
        }

        return Authentication(type="Login", parameters=parameters)


class ConfigHelpers:
    def __init__(self):
        self.user_functions = UserFunctions()

    def fill_in_user_parameters(
        self, conf_objects: dict, user_param: dict, evaluate_conf_objects_functions: bool = True
    ):
        """
        This method replaces user parameter references via attr + parses functions inside user parameters,
        evaluates them and fills in the resulting values

        Args:
            conf_objects: Configuration that contains the references via {"attr": "key"} to user parameters or function
                            definitions
            user_param: User parameters that are used to fill in the values

        Returns:

        """
        # time references
        conf_objects = self.fill_in_time_references(conf_objects)
        user_param = self.fill_in_time_references(user_param)
        # convert to string minified
        steps_string = json.dumps(conf_objects, separators=(",", ":"))
        # dirty and ugly replace
        for key in user_param:
            if isinstance(user_param[key], dict):
                # in case the parameter is function, validate, execute and replace value with result
                res = self.perform_custom_function(key, user_param[key], user_param)
                user_param[key] = res

            lookup_str = '{"attr":"' + key + '"}'
            steps_string = steps_string.replace(lookup_str, '"' + str(user_param[key]) + '"')
        new_steps = json.loads(steps_string)
        non_matched = nested_lookup("attr", new_steps)

        if evaluate_conf_objects_functions:
            for key in new_steps:
                if isinstance(new_steps[key], dict):
                    # in case the parameter is function, validate, execute and replace value with result
                    res = self.perform_custom_function(key, new_steps[key], user_param)
                    new_steps[key] = res

        if non_matched:
            raise ValueError(
                "Some user attributes [{}] specified in parameters "
                'are not present in "user_parameters" json_path.'.format(non_matched)
            )
        return new_steps

    @staticmethod
    def fill_in_time_references(conf_objects: dict):
        """
        This method replaces user parameter references via attr + parses functions inside user parameters,
        evaluates them and fills in the resulting values

        Args:
            conf_objects: Configuration that contains the references via {"attr": "key"} to user parameters or function
                            definitions

        Returns:

        """
        # convert to string minified
        steps_string = json.dumps(conf_objects, separators=(",", ":"))
        # dirty and ugly replace

        new_cfg_str = steps_string.replace('{"time":"currentStart"}', f"{int(time.time())}")
        new_cfg_str = new_cfg_str.replace('{"time":"previousStart"}', f"{int(time.time())}")
        new_config = json.loads(new_cfg_str)
        return new_config

    def perform_custom_function(self, key: str, function_cfg: dict, user_params: dict):
        """
        Perform custom function recursively (may be nested)
        Args:
            key: key of the user parameter wher the function is
            function_cfg: conf of the function
            user_params:

        Returns:

        """
        function_cfg = self.fill_in_time_references(function_cfg)
        if not isinstance(function_cfg, dict):
            # in case the function was evaluated as time
            return function_cfg

        elif function_cfg.get("attr"):
            return user_params[function_cfg["attr"]]

        if not function_cfg.get("function"):
            for key in function_cfg:
                function_cfg[key] = self.perform_custom_function(key, function_cfg[key], user_params)

        new_args = []
        if function_cfg.get("args"):
            for arg in function_cfg.get("args"):
                if isinstance(arg, dict):
                    arg = self.perform_custom_function(key, arg, user_params)
                new_args.append(arg)
            function_cfg["args"] = new_args
        if isinstance(function_cfg, dict) and not function_cfg.get("function"):
            return function_cfg
        return self.user_functions.execute_function(function_cfg["function"], *function_cfg.get("args", []))
