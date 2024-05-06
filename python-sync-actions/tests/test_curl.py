import unittest

from keboola.component import UserException

from actions import curl
from actions.curl import JobTemplate


class TestCurl(unittest.TestCase):
    def test_x_form_urlencoded_explicit(self):
        command = 'curl -d "param1=value1&param2=value2" -H "Content-Type: application/x-www-form-urlencoded" -X POST http://localhost:3000/blahblah'

        result = curl.build_job_from_curl(command)
        expected = JobTemplate(endpoint='http://localhost:3000/blahblah', children=[], method='POST', dataType='.',
                               dataField='.',
                               params={'param1': 'value1', 'param2': 'value2'},
                               headers={'Content-Type': 'application/x-www-form-urlencoded'})
        self.assertEqual(result, expected)

    def test_x_form_urlencoded_implicit(self):
        command = 'curl -d "param1=value1&param2=value2" -X POST http://localhost:3000/blahblah'

        result = curl.build_job_from_curl(command)
        expected = JobTemplate(endpoint='http://localhost:3000/blahblah', children=[], method='POST', dataType='.',
                               dataField='.',
                               params={'param1': 'value1', 'param2': 'value2'},
                               headers={'Content-Type': 'application/x-www-form-urlencoded'})
        self.assertEqual(result, expected)

    def test_json_explicit(self):
        command = 'curl -X POST -H "Content-Type: application/json" -d \'{"key1":"value1", "key2":{"nested":"value2"}}\' http://localhost:3000/endpoint'

        result = curl.build_job_from_curl(command)
        expected = JobTemplate(endpoint='http://localhost:3000/endpoint', children=[], method='POST', dataType='.',
                               dataField='.',
                               params={'key1': 'value1', 'key2': {'nested': 'value2'}},
                               headers={'Content-Type': 'application/json'})
        self.assertEqual(result, expected)

    def test_json_query_params_fail(self):
        command = 'curl -X POST -H "Content-Type: application/json" -d \'{"key1":"value1", "key2":{"nested":"value2"}}\' http://localhost:3000/endpoint?param1=value1'

        with self.assertRaises(UserException) as context:
            curl.build_job_from_curl(command)

        self.assertEqual(str(context.exception),
                         'Query parameters are not supported for POST requests with JSON content type')

    def test_unknown_method_fails(self):
        command = 'curl -X PATCH -H "Content-Type: application/json" -d \'{"key1":"value1", "key2":{"nested":"value2"}}\' http://localhost:3000/endpoint'

        with self.assertRaises(UserException) as context:
            curl.build_job_from_curl(command)

        self.assertEqual(str(context.exception),
                         'Unsupported method PATCH, only GET, POST with JSON and POST with form data are supported.')
