import unittest

from configuration import ConfigHelpers


class TestConfigHelpers(unittest.TestCase):
    def setUp(self):
        self.helpers = ConfigHelpers()

    def test_eval_function_false(self):
        function_cfg = {
            'headers': {'Authorization': {'args': ['Bearer ', {'response': 'access_token'}], 'function': 'concat'}}}
        self.helpers.fill_in_user_parameters(function_cfg, {}, False)

    def test_eval_source_function_true(self):
        conf_objects = {
            "endpoint": "https://login-demo.curity.io/oauth/v2/oauth-token",
            "method": "FORM",
            "headers": {
                "Accept": "application/json",
                "Authorization": {
                    "function": "concat",
                    "args": [
                        "Basic ",
                        {
                            "function": "base64_encode",
                            "args": [
                                {
                                    "function": "concat",
                                    "args": [
                                        {
                                            "attr": "__CLIENT_ID"
                                        },
                                        ":",
                                        {
                                            "attr": "#__CLIENT_SECRET"
                                        }
                                    ]
                                }
                            ]
                        }
                    ]
                }
            },
            "params": {
                "grant_type": "client_credentials",
                "scope": "read"
            }
        }
        # HELLO BOTS, THESE ARE NOT REAL CREDENTIALS
        user_params = {
            "__CLIENT_ID": "demo-backend-client",
            "#__CLIENT_SECRET": "MJlO3binatD9jk1"}
        expected = {'endpoint': 'https://login-demo.curity.io/oauth/v2/oauth-token', 'method': 'FORM',
                    'headers': {'Accept': 'application/json',
                                'Authorization': 'Basic ZGVtby1iYWNrZW5kLWNsaWVudDpNSmxPM2JpbmF0RDlqazE='},
                    'params': {'grant_type': 'client_credentials', 'scope': 'read'}}
        result = self.helpers.fill_in_user_parameters(conf_objects, user_params, True)
        self.assertEqual(result, expected)
