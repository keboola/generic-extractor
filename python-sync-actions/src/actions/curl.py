import json
import shlex
import subprocess
from dataclasses import dataclass, field
from typing import List, Any, Dict
from urllib.parse import urlparse

from keboola.component import UserException


@dataclass
class JobTemplate:
    endpoint: str = ""
    children: List[Any] = field(default_factory=list)
    method: str = "GET"
    dataType: str = "."
    dataField: str = "."
    params: Dict[str, Any] = field(default_factory=dict)
    headers: Dict[str, Any] = field(default_factory=dict)

    def to_dict(self):
        return {
            "__NAME": self.endpoint,
            "endpoint": self.endpoint,
            "children": [child.to_dict() for child in self.children],
            "method": self.method,
            "dataType": self.dataType,
            "dataField": self.dataField,
            "params": self.params,
            "headers": self.headers
        }


def is_url(url: str) -> bool:
    try:
        result = urlparse(url)
        return all([result.scheme, result.netloc])
    except Exception:
        return False


def retrieve_url(curl_command: str) -> str:
    """
    Retrieve the URL from the cURL command
    Args:
        curl_command:

    Returns:

    """

    curl_command = curl_command.replace("\\\n", " ")

    tokens = shlex.split(curl_command)
    if tokens[0] != "curl":
        raise ValueError("Not a valid cURL command")

    # find valid URL
    url = ''
    for t in tokens:
        token_string = t
        if t.startswith("'") and t.endswith("'"):
            token_string = t[1:-1]

        if is_url(token_string):
            url = token_string
            break
    if not url:
        raise ValueError("No valid URL found in the cURL command")

    return url


def normalize_url_in_curl(curl_command: str) -> str:
    """
    Normalize the URL in the cURL command to remove globs and other unsupported characters
    Args:
        curl_command:

    Returns:

    """
    unsupported_characters = ['{', '}']
    url = retrieve_url(curl_command)
    new_url = url
    for character in unsupported_characters:
        new_url = new_url.replace(character, '_')

    new_curl_command = curl_command.replace(url, new_url)
    return new_curl_command


def parse_curl(curl_command: str) -> dict:
    """
    Running the curlconverter to parse the cURL command, returns the JSON representation of the cURL command

    Example:
    {'url': 'https://api.example.com', 'raw_url': 'https://api.example.com?test=testvalue',
    'method': 'post',
    'headers': {'accept': 'application/json', 'Content-Type': 'application/json'},
    'queries': {'test': 'testvalue'}, 'data': {'key': 'value'}}

    https://github.com/curlconverter/curlconverter/
    Args:
        curl_command: The entire cURL command string.

    Returns: the JSON representation of the cURL command

    """
    # normalize the URL
    curl_command = normalize_url_in_curl(curl_command)
    escaped_curl_command = curl_command.replace("'", "'\\''")
    command = f"echo '{escaped_curl_command}' | curlconverter --language json -"

    # Running the command
    result = subprocess.run(command, shell=True, text=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE)

    # Getting the results
    stdout_str = result.stdout
    stderr_str = result.stderr

    if result.returncode != 0:
        raise UserException(f"Error parsing cURL: {stderr_str}")

    return json.loads(stdout_str)


def _get_endpoint_path(base_url: str, url: str) -> str:
    """
    Get the endpoint path from the URL and the base_url
    Args:
        base_url: The base URL
        url: The URL

    Returns: The endpoint path

    """
    if url.startswith(base_url):
        return url[len(base_url):]
    else:
        return url


def _get_content_type(headers: dict) -> str:
    """
    Get the content type from the headers
    Args:
        headers:

    Returns:

    """
    for key, value in headers.items():
        if key.lower() == 'content-type':
            return value

    return ''


def build_job_from_curl(curl_command: str, base_url: str = None, is_child_job: bool = False) -> JobTemplate:
    """
    Get represenatiton of the JOB section from the curl_command
    Args:
        curl_command:

    Returns:

    """
    job_template = JobTemplate()
    # Parsing the cURL command
    parsed_curl = parse_curl(curl_command)

    if base_url:
        job_template.endpoint = _get_endpoint_path(base_url, parsed_curl['url'])
    else:
        job_template.endpoint = parsed_curl['url']

    parsed_method = parsed_curl['method'].upper()
    content_type = _get_content_type(parsed_curl.get('headers', {})).lower()
    if parsed_method == "POST" and content_type == "application/json":
        job_template.params = parsed_curl.get('data', {})
        job_template.method = "POST"

        if parsed_curl.get('queries'):
            raise UserException("Query parameters are not supported for POST requests with JSON content type")

    elif parsed_method == "POST" and content_type == "application/x-www-form-urlencoded":
        job_template.params = parsed_curl.get('data', {})
        job_template.method = "FORM"
    elif parsed_method == "GET":
        job_template.params = parsed_curl.get('queries', {})
        job_template.method = "GET"
    else:
        raise UserException(f"Unsupported method {parsed_method}, "
                            f"only GET, POST with JSON and POST with form data are supported.")

    job_template.method = parsed_curl['method'].upper()
    job_template.headers = parsed_curl.get('headers', {})
    job_template.dataType = job_template.endpoint.split('/')[-1]

    return job_template
