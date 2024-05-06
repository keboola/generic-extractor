from typing import Tuple, Dict

import requests
from keboola.component import UserException
from keboola.http_client import HttpClient
from requests.adapters import HTTPAdapter
from requests.exceptions import HTTPError, InvalidJSONError, ConnectionError
from urllib3 import Retry

from http_generic.auth import AuthMethodBase


# TODO: add support for pagination methods
class GenericHttpClient(HttpClient):

    def __init__(self, base_url: str,
                 default_http_header: Dict = None,
                 default_params: Dict = None,
                 auth_method: AuthMethodBase = None,
                 max_retries: int = 10,
                 backoff_factor: float = 0.3,
                 status_forcelist: Tuple[int, ...] = (500, 502, 504)
                 ):
        super().__init__(base_url=base_url, max_retries=max_retries, backoff_factor=backoff_factor,
                         status_forcelist=status_forcelist,
                         default_http_header=default_http_header, default_params=default_params)

        self._auth_method = auth_method

    def login(self):
        """
        Perform login based on auth method

        """
        # perform login
        if self._auth_method:
            self._auth = self._auth_method.login()

    def send_request(self, method, endpoint_path, **kwargs):
        try:
            resp = self._request_raw(method=method, endpoint_path=endpoint_path, is_absolute_path=False, **kwargs)
            resp.raise_for_status()
            return resp
        except HTTPError as e:
            if e.response.status_code in self.status_forcelist:
                message = f'Request "{method}: {endpoint_path}" failed, too many retries. ' \
                          f'Status Code: {e.response.status_code}. Response: {e.response.text}'
            else:
                message = f'Request "{method}: {endpoint_path}" failed with non-retryable error. ' \
                          f'Status Code: {e.response.status_code}. Response: {e.response.text}'
            raise UserException(message) from e
        except InvalidJSONError:
            message = f'Request "{method}: {endpoint_path}" failed. The JSON payload is invalid (more in detail). ' \
                      f'Verify the datatype conversion.'
            data = kwargs.get('data') or kwargs.get('json')
            raise UserException(message, data)
        except ConnectionError as e:
            message = f'Request "{method}: {endpoint_path}" failed with the following error: {e}'
            raise UserException(message) from e

    def build_url(self, base_url, endpoint_path):
        self.base_url = base_url
        return self._build_url(endpoint_path)

    # override to continue on retry error
    def _requests_retry_session(self, session=None):
        session = session or requests.Session()
        retry = Retry(
            total=self.max_retries,
            read=self.max_retries,
            connect=self.max_retries,
            backoff_factor=self.backoff_factor,
            status_forcelist=self.status_forcelist,
            allowed_methods=self.allowed_methods,
            raise_on_status=False
        )
        adapter = HTTPAdapter(max_retries=retry)
        session.mount('http://', adapter)
        session.mount('https://', adapter)
        return session
