import os
import unittest
from pathlib import Path

from component import Component


class TestComponent(unittest.TestCase):

    def setUp(self) -> None:
        self.tests_dir = Path(__file__).absolute().parent.joinpath('data_tests').as_posix()

    def _get_test_component(self, test_name):
        test_dir = os.path.join(self.tests_dir, test_name)
        os.environ['KBC_DATADIR'] = test_dir
        return Component()

    # @patch('http_generic.client.GenericHttpClient.send_request')
    # def test_001_nested(self, mock_send_request):
    #
    #     mock_send_request.return_value.json.return_value = json.loads(
    #         Path(self.tests_dir, self._testMethodName, 'response.json').read_text())
    #
    #     component = self._get_test_component(self._testMethodName)
    #     output = component.run()
    #     expected_output = json.loads(Path(self.tests_dir, self._testMethodName, 'output.json').read_text())
    #     self.assertEqual(output, expected_output)
    #
    # @patch('http_generic.client.GenericHttpClient.send_request')
    # def test_002_nested(self, mock_send_request):
    #
    #     mock_send_request.return_value.json.return_value = json.loads(
    #         Path(self.tests_dir, self._testMethodName, 'response.json').read_text())
    #
    #     component = self._get_test_component(self._testMethodName)
    #     output = component.run()
    #     expected_output = json.loads(Path(self.tests_dir, self._testMethodName, 'output.json').read_text())
    #     self.assertEqual(output, expected_output)

    def test_003_oauth_cc(self):
        component = self._get_test_component(self._testMethodName)
        results, response, log, error_message = component.make_call()
        expected_data = [{'id': '321', 'status': 'get'}, {'id': 'girlfriend', 'status': 'imaginary'}]
        self.assertEqual(results, expected_data)
        self.assertTrue(response.request.headers['Authorization'].startswith('Bearer '))

    def test_003_oauth_cc_filtered(self):
        component = self._get_test_component('test_003_oauth_cc')
        results = component.test_request()
        self.assertEqual(results['request']['headers']['Authorization'], '--HIDDEN--')

    def test_004_oauth_cc_post(self):
        component = self._get_test_component(self._testMethodName)
        results, response, log, error_message = component.make_call()
        expected_data = [{'id': '321', 'status': 'get'}, {'id': 'girlfriend', 'status': 'imaginary'}]
        self.assertEqual(results, expected_data)
        self.assertTrue(response.request.headers['Authorization'].startswith('Bearer '))

    def test_004_oauth_cc_post_filtered(self):
        component = self._get_test_component('test_004_oauth_cc_post')
        results = component.test_request()
        self.assertEqual(results['request']['headers']['Authorization'], '--HIDDEN--')

    def test_005_post(self):
        component = self._get_test_component(self._testMethodName)
        output = component.test_request()
        expected_data = [{'id': '123', 'status': 'post'}, {'id': 'potato', 'status': 'mashed'}]
        self.assertEqual(output['response']['data'], expected_data)
        expected_request_data = '{"parameter": "value"}'
        self.assertEqual(output['request']['data'], expected_request_data)
        # url params are dropped
        self.assertEqual(output['request']['url'], 'http://private-834388-extractormock.apiary-mock.com/post')
        # correct content type
        self.assertEqual(output['request']['headers']['Content-Type'], 'application/json')

    def test_006_post_fail(self):
        component = self._get_test_component(self._testMethodName)
        output = component.test_request()

        self.assertEqual(output['response']['status_code'], 404)
        self.assertEqual(output['response']['reason'], 'Not Found')

        expected_request_data = '{"parameter": "value"}'
        self.assertEqual(output['request']['data'], expected_request_data)

    def test_006_post_form(self):
        component = self._get_test_component(self._testMethodName)
        output = component.test_request()
        expected_data = [{'id': '123', 'status': 'post'}, {'id': 'potato', 'status': 'mashed'}]
        self.assertEqual(output['response']['data'], expected_data)
        expected_request_data = 'parameter=value'
        self.assertEqual(output['request']['data'], expected_request_data)
        # url params are dropped
        self.assertEqual(output['request']['url'], 'http://private-834388-extractormock.apiary-mock.com/post')
        # request method is POST
        self.assertEqual(output['request']['method'], 'POST')
        # correct content type
        self.assertEqual(output['request']['headers']['Content-Type'], 'application/x-www-form-urlencoded')

    def test_009_empty_datafield(self):
        component = self._get_test_component(self._testMethodName)
        results, response, log, error_message = component.make_call()
        expected_data = [
            {
                "id": "1.0",
                "status": "first"
            },
            {
                "id": "1.1",
                "status": "page"
            }
        ]
        self.assertEqual(results, expected_data)

    def test_parse_data_null_datafield(self):
        component = self._get_test_component('test_009_empty_datafield')
        # test array of primitives
        data = {"some_property": "asd",
                "some_object": {"some_property": "asd"},
                "data": [1, 2, 3]
                }
        results = component._parse_data(data, None)
        self.assertEqual(results, data['data'])

        # test array of arrays
        data = {"some_property": "asd",
                "some_object": {"some_property": "asd"},
                "data": [[{"col": "a"}], [{"col": "b"}]]
                }
        results = component._parse_data(data, None)
        self.assertEqual(results, data['data'])


if __name__ == '__main__':
    unittest.main()
