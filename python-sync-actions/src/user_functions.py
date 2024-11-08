import base64
import hashlib
import subprocess
import time

import keboola.utils as kbcutils
from keboola.component import UserException


def perform_shell_command(command: str, context_detail: str = '') -> tuple[str, str]:
    """
    Perform shell command
    Args:
        command: shell command
        context_detail: additional context detail for exception handling

    Returns: command output

    """
    # Running the command
    result = subprocess.run(command, shell=True, text=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE)

    # Getting the results
    stdout_str = result.stdout
    stderr_str = result.stderr

    if result.returncode != 0:
        raise UserException(f"Error while performing {context_detail}: {stderr_str}")
    return stdout_str, stderr_str


class UserFunctions:
    """
    Custom function to be used in configruation
    """

    def validate_function_name(self, function_name):
        supp_functions = self.get_supported_functions()
        if function_name not in self.get_supported_functions():
            raise ValueError(
                F"Specified user function [{function_name}] is not supported! "
                F"Supported functions are {supp_functions}")

    @classmethod
    def get_supported_functions(cls):
        return [method_name for method_name in dir(cls)
                if callable(getattr(cls, method_name)) and not method_name.startswith('__')
                and method_name not in ['validate_function_name', 'get_supported_functions', 'execute_function']]

    def execute_function(self, function_name, *pars):
        self.validate_function_name(function_name)
        return getattr(UserFunctions, function_name)(self, *pars)

    # ############## USER FUNCTIONS
    def string_to_date(self, date_string, date_format='%Y-%m-%d'):
        start_date, end_date = kbcutils.parse_datetime_interval(date_string, date_string)
        return start_date.strftime(date_format)

    def concat(self, *args):
        return ''.join(args)

    def base64_encode(self, s):
        return base64.b64encode(s.encode('utf-8')).decode('utf-8')

    def md5(self, s):
        return hashlib.md5(s.encode('utf-8')).hexdigest()

    def sha1(self, s):
        return hashlib.sha1(s.encode('utf-8')).hexdigest()

    def implode(self, delimiter, values):
        return delimiter.join(values)

    def hash_hmac(self, algorithm, key, message):
        """
        Execute PHP hash_hmac function
        Args:
            algorithm:
            key:
            message:

        Returns:

        """

        command = f"php -r 'echo hash_hmac(\"{algorithm}\", \"{message}\", \"{key}\");'"
        stdout, stderr = perform_shell_command(command, 'hash_hmac function')

        return stdout

    def hash(self, algorithm, message):
        """
            Execute PHP hash function
            Args:
                algorithm:
                message:

            Returns:

            """

            command = f"php -r 'echo hash(\"{algorithm}\", \"{message}\");'"
            stdout, stderr = perform_shell_command(command, 'hash function')

            return stdout

    def time(self):
        return int(time.time())

    def date(self, format_string, timestamp=None):
        """
        Execute PHP date function
        Args:
            format_string:
            timestamp:

        Returns:

            """

        command = f"php -r 'echo date(\"{format_string}\", {timestamp or int(time.time())});'"
        stdout, stderr = perform_shell_command(command, 'date function')

        return stdout

    def strtotime(self, string, base_time=None):
        """
        Execute PHP strtotime function
        Args:
            string:
            base_time:

        Returns:

        """

        command = f"php -r 'echo strtotime(\"{string}\", {base_time or int(time.time())});'"
        stdout, stderr = perform_shell_command(command, 'strotime function')

        return int(stdout)

    def sprintf(self, format_string, *values):
        return format_string % values

    def ifempty(self, test_value: str, return_value: str | bool | int | dict) -> str | bool | int | dict:
        if not test_value:
            return return_value
