import dataclasses
import json
from dataclasses import dataclass, field
from enum import Enum
from typing import List, Tuple, Optional

import dataconf


class ConfigurationBase:

    @staticmethod
    def _convert_private_value(value: str):
        return value.replace('"#', '"pswd_')

    @staticmethod
    def _convert_private_value_inv(value: str):
        if value and value.startswith('pswd_'):
            return value.replace('pswd_', '#', 1)
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
        return [cls._convert_private_value_inv(f.name) for f in dataclasses.fields(cls)
                if f.default == dataclasses.MISSING and f.default_factory == dataclasses.MISSING]


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
class ApiConfig(ConfigurationBase):
    base_url: str
    default_query_parameters: dict = field(default_factory=dict)
    default_headers: dict = field(default_factory=dict)
    authentication: Authentication = None
    retry_config: RetryConfig = field(default_factory=RetryConfig)
    ssl_verification: bool = True
    timeout: float = None


@dataclass
class ApiRequest(ConfigurationBase):
    method: str
    endpoint_path: str
    headers: dict = field(default_factory=dict)
    query_parameters: dict = field(default_factory=dict)
    continue_on_failure: bool = False


@dataclass
class DataPath(ConfigurationBase):
    path: str
    delimiter: str


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
    data_path: DataPath = field(default_factory=DataPath)


class ConfigurationKeysV2(Enum):
    api = 'api'
    user_parameters = 'user_parameters'
    request_options = 'request_options'

    @classmethod
    def list(cls):
        return list(map(lambda c: c.value, cls))


def convert_to_v2(configuration: dict) -> Configuration:
    """
    Convert configuration to v2 format
    Args:
        configuration: Configuration in v1 format

    Returns: Configuration in v2 format

    """
    user_parameters = build_user_parameters(configuration)

    api_json = configuration.get('api')
    base_url = api_json.get('baseUrl')
    default_headers = api_json.get('http').get('headers', {})
    default_query_parameters = api_json.get('http').get('defaultOptions', {}).get('params', {})

    api_config = ApiConfig(base_url=base_url, default_headers=default_headers,
                           default_query_parameters=default_query_parameters)

    api_config.retry_config = build_retry_config(configuration)
    api_config.authentication = AuthMethodConverter.convert(configuration)

    api_request, request_content, data_path = build_api_request(configuration)

    return Configuration(api=api_config,
                         request_parameters=api_request,
                         request_content=request_content,
                         user_parameters=user_parameters,
                         data_path=data_path
                         )


def build_retry_config(configuration: dict) -> RetryConfig:
    """
    Build retry configuration from configuration
    Args:
        configuration: Configuration in v2 format

    Returns: Retry configuration

    """
    http_section = configuration.get('api').get('http', {})
    return RetryConfig(max_retries=http_section.get('maxRetries', 10),
                       codes=http_section.get('codes', (500, 502, 503, 504, 408, 420, 429)))


def build_user_parameters(configuration: dict) -> dict:
    """
    Build user parameters from configuration
    Args:
        configuration: Configuration in v2 format

    Returns: User parameters

    """
    config_excluded_keys = ['__AUTH_METHOD', '__NAME', '#__BEARER_TOKEN', 'jobs', 'outputBucket', 'incrementalOutput',
                            'debug', 'mappings', ' #username', '#password']
    user_parameters = {}
    for key, value in configuration['config'].items():
        if key not in config_excluded_keys:
            user_parameters[key] = value
    return user_parameters


def build_api_request(configuration: dict) -> Tuple[ApiRequest, RequestContent, DataPath]:
    """
    Build API request and content from configuration
    Args:
        configuration: Configuration in v2 format

    Returns: API request, Request Content

    """
    job_path: str = configuration.get('__SELECTED_JOB')
    if not job_path:
        raise ValueError('Selected job not found in the configuration')
    if '_' in job_path:
        parent, child = job_path.split('_')
    else:
        parent, child = job_path, None # noqa

    jobs_section: list[dict] = configuration.get('config', {}).get('jobs')
    if not jobs_section:
        raise ValueError('Jobs section not found in the configuration, no endpoint specified')

    # TODO: support recursive child-job config. E.g. have chained/list of ApiRequests objects instead of just one
    endpoint_config = jobs_section[int(parent)]

    method = endpoint_config.get('method', 'GET')

    match method:
        case 'GET':
            request_content = RequestContent(ContentType.none)
        case 'POST':
            request_content = RequestContent(ContentType.json,
                                             body=endpoint_config.get('params', {}))
        case 'FORM':
            request_content = RequestContent(ContentType.form,
                                             body=endpoint_config.get('params', {}))
        case _:
            raise ValueError(f'Unsupported method: {method}')

    endpoint_path = endpoint_config.get('endpoint')

    data_field = endpoint_config.get('dataField')

    if isinstance(data_field, dict):
        path = data_field.get('path')
        delimiter = data_field.get("delimiter", ".")
    else:
        path = data_field
        delimiter = "."

    return (ApiRequest(method=method,
                       endpoint_path=endpoint_path,
                       headers=endpoint_config.get('headers', {}),
                       query_parameters=endpoint_config.get('params', {})), request_content,
            DataPath(path=path, delimiter=delimiter))


class AuthMethodConverter:
    SUPPORTED_METHODS = ['basic', 'api-key', 'bearer']

    @classmethod
    def convert(cls, config_parameters: dict) -> Authentication | None:
        """

        Args:
            config_parameters (dict):
        """
        auth_method = config_parameters.get('config').get('__AUTH_METHOD', None)
        if not auth_method:
            return None

        methods = {
            'basic': cls._convert_basic,
            'bearer': cls._convert_bearer,
            'api-key': cls._convert_api_key
        }

        func = methods.get(auth_method)

        if func:
            return func(config_parameters)
        else:
            raise ValueError(f'Unsupported auth method: {auth_method}')

    @classmethod
    def _convert_basic(cls, config_parameters: dict) -> Authentication:
        username = config_parameters.get('config').get('#username')
        password = config_parameters.get('config').get('#password')
        if not username or not password:
            raise ValueError('Username or password not found in the BasicAuth configuration')

        return Authentication(type='BasicHttp', parameters={'username': username, '#password': password})

    @classmethod
    def _convert_api_key(cls, config_parameters: dict) -> Authentication:
        pass

    @classmethod
    def _convert_bearer(cls, config_parameters: dict) -> Authentication:
        token = config_parameters.get('config').get('#__BEARER_TOKEN')
        if not token:
            raise ValueError('Bearer token not found in the Bearer Token Authentication configuration')

        return Authentication(type='BearerToken', parameters={'#token': token})
