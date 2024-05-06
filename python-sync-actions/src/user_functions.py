import base64
import datetime
import hashlib
import hmac
import time

import dateutil.parser
import keboola.utils as kbcutils


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
        return hmac.new(bytes(message, 'UTF-8'), key.encode(), getattr(hashlib, algorithm)).hexdigest()

    def time(self):
        return int(time.time())

    def date(self, format_string, timestamp=None):
        date_obj = datetime.datetime.fromtimestamp(timestamp) if timestamp is not None else datetime.datetime.now()
        return date_obj.strftime(self.php_to_python_date_format(format_string))

    def php_to_python_date_format(self, php_format):
        replacements = {
            'Y': '%Y', 'y': '%y', 'm': '%m', 'n': '%m', 'd': '%d', 'j': '%d',
            'H': '%H', 'G': '%H', 'h': '%I', 'g': '%I', 'i': '%M', 's': '%S',
            'a': '%p', 'A': '%p'
        }
        python_format = php_format
        for php, python in replacements.items():
            python_format = python_format.replace(php, python)
        return python_format

    def strtotime(self, string, base_time=None):
        if not base_time:
            date = dateutil.parser.parse(string)
        else:
            if isinstance(base_time, str):
                base_time = dateutil.parser.parse(base_time)
            else:
                base_time = datetime.datetime.fromtimestamp(base_time)
            date = kbcutils.get_past_date(string, base_time)

        return int(date.timestamp())

    def sprintf(self, format_string, *values):
        return format_string % values
