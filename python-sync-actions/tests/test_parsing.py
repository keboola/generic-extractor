import os
import unittest
from pathlib import Path

from src.component import Component


class TestComponent(unittest.TestCase):

    def setUp(self) -> None:
        self.tests_dir = Path(__file__).absolute().parent.joinpath('parsing').as_posix()

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


if __name__ == '__main__':
    unittest.main()
