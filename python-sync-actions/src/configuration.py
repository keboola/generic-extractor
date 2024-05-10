import dataclasses
import json
from dataclasses import dataclass, field
from enum import Enum
from typing import List, Tuple, Optional
from keboola.component.exceptions import UserException


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
    placeholders: dict = field(default_factory=dict)
    headers: dict = field(default_factory=dict)
    query_parameters: dict = field(default_factory=dict)
    continue_on_failure: bool = False
    nested_job: dict = field(default_factory=dict)


@dataclass
class DataPath(ConfigurationBase):
    path: str = '.'
    delimiter: str = '.'

    def to_dict(self):
        return {
            'path': self.path,
            'delimiter': self.delimiter
        }


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


def convert_to_v2(configuration: dict) -> list[Configuration]:
    """
    Convert configuration to v2 format
    Args:
        configuration: Configuration in v1 format

    Returns: Configuration in v2 format

    """
    user_parameters = build_user_parameters(configuration)

    api_json = configuration.get('api', {})
    base_url = api_json.get('baseUrl', '')
    default_headers = api_json.get('http', {}).get('headers', {})
    default_query_parameters = api_json.get('http', {}).get('defaultOptions', {}).get('params', {})

    api_config = ApiConfig(base_url=base_url, default_headers=default_headers,
                           default_query_parameters=default_query_parameters)

    api_config.retry_config = build_retry_config(configuration)
    api_config.authentication = AuthMethodConverter.convert(configuration)

    requests = []

    jobs = build_api_request(configuration)

    for api_request, request_content, data_path in jobs:

        requests.append(
                        Configuration(api=api_config,
                                      request_parameters=api_request,
                                      request_content=request_content,
                                      user_parameters=user_parameters,
                                      data_path=data_path
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
    http_section = configuration.get('api', {}).get('http', {})
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
    for key, value in configuration.get('config', {}).items():
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

    job_path: str = configuration.get('__SELECTED_JOB')

    if not job_path:
        raise UserException('Job path not found in the configuration')

    selected_jobs = job_path.split('_')

    nested_path = []

    for index in selected_jobs:
        nested_path.append(int(index))

        endpoint_config = configuration.get('config', {}).get('jobs')[nested_path[0]]

        if not endpoint_config:
            raise ValueError('Jobs section not found in the configuration, no endpoint specified')

        for child in nested_path[1:]:
            try:
                endpoint_config = endpoint_config.get('children', [])[child]
            except IndexError:
                raise ValueError('Jobs section not found in the configuration, no endpoint specified')

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

        placeholders = endpoint_config.get('placeholders', {})


        if isinstance(data_field, dict):
            path = data_field.get('path')
            delimiter = data_field.get("delimiter", ".")
        else:
            path = data_field
            delimiter = "."

        result_requests.append(
            (ApiRequest(method=method,
                        endpoint_path=endpoint_path,
                        placeholders=placeholders,
                        headers=endpoint_config.get('headers', {}),
                        query_parameters=endpoint_config.get('params', {}),),
             request_content,
             DataPath(path=path, delimiter=delimiter)))

    return result_requests


class AuthMethodConverter:
    SUPPORTED_METHODS = ['basic', 'api-key', 'bearer']

    @classmethod
    def convert(cls, config_parameters: dict) -> Authentication | None:
        """

        Args:
            config_parameters (dict):
        """
        auth_method = config_parameters.get('config', {}).get('__AUTH_METHOD', None)
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
        username = config_parameters.get('config').get('username')
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
