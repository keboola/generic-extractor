import inspect
from abc import ABC, abstractmethod
from typing import Callable, Union, Dict

from requests.auth import AuthBase, HTTPBasicAuth


class AuthBuilderError(Exception):
    pass


class AuthMethodBase(ABC):
    """
    Base class to implement the authentication method. To mark secret constructor parameters prefix them with __
    e.g. __init__(self, username, __password)
    """

    @abstractmethod
    def login(self):
        """
        Perform steps to login and returns requests.aut.AuthBase callable that modifies the request.

        """
        pass


class AuthMethodBuilder:

    @classmethod
    def build(cls, method_name: str, **parameters):
        """

        Args:
            method_name:
            **parameters: dictionary of named parameters. Note that parameters prefixed # will be converted to __

        Returns:

        """
        supported_actions = cls.get_methods()

        if method_name not in list(supported_actions.keys()):
            raise AuthBuilderError(f'{method_name} is not supported auth method, '
                                   f'supported values are: [{list(supported_actions.keys())}]')
        parameters = cls._convert_secret_parameters(supported_actions[method_name], **parameters)
        cls._validate_method_arguments(supported_actions[method_name], **parameters)

        return supported_actions[method_name](**parameters)

    @staticmethod
    def _validate_method_arguments(method: object, **args):
        class_prefix = f"_{method.__name__}__"
        arguments = [p for p in inspect.signature(method.__init__).parameters if p != 'self']
        missing_arguments = []
        for p in arguments:
            if p not in args:
                missing_arguments.append(p.replace(class_prefix, '#'))
        if missing_arguments:
            raise AuthBuilderError(f'Some arguments of method {method.__name__} are missing: {missing_arguments}')

    @staticmethod
    def _convert_secret_parameters(method: object, **parameters):
        new_parameters = {}
        for p in parameters:
            new_parameters[p.replace('#', f'_{method.__name__}__')] = parameters[p]
        return new_parameters

    @staticmethod
    def get_methods() -> Dict[str, Callable]:
        supported_actions = {}
        for c in AuthMethodBase.__subclasses__():
            supported_actions[c.__name__] = c
        return supported_actions

    @classmethod
    def get_supported_methods(cls):
        return list(cls.get_methods().keys())


# ########### SUPPORTED AUTHENTICATION METHODS

# TODO: Add all supported authentication methods that will be covered by the UI

class BasicHttp(AuthMethodBase):

    def __init__(self, username, __password):
        self.username = username
        self.password = __password

    def login(self) -> Union[AuthBase, Callable]:
        return HTTPBasicAuth(username=self.username, password=self.password)

    def __eq__(self, other):
        return all([
            self.username == getattr(other, 'username', None),
            self.password == getattr(other, 'password', None)
        ])


class BearerToken(AuthMethodBase, AuthBase):

    def __init__(self, __token):
        self.token = __token

    def login(self) -> Union[AuthBase, Callable]:
        return self

    def __eq__(self, other):
        return all([
            self.token == getattr(other, 'token', None)
        ])

    def __ne__(self, other):
        return not self == other

    def __call__(self, r):
        r.headers['authorization'] = f"Bearer {self.token}"
        return r


class ApiKey(AuthMethodBase, AuthBase):
    def __init__(self, key: str, token: str, position: str):
        self.token = token
        self.key = key
        self.position = position

    def login(self) -> Union[AuthBase, Callable]:
        return self

    def __eq__(self, other):
        return all([
            self.token == getattr(other, 'token', None)
        ])

    def __ne__(self, other):
        return not self == other

    def __call__(self, r):
        if self.position == 'headers':
            r.headers[self.key] = f"{self.token}"

        elif self.position == 'defaultOptions':
            r.body = {"defaultOptions": {self.key: f"{self.token}"}}
        return r
