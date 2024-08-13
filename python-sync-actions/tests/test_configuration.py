import unittest

import configuration
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

    def test_query_parameters_dropped_in_post_mode(self):
        config = {
            "__SELECTED_JOB": "0",
            "config": {
                "userData": {},
                "outputBucket": "",
                "incrementalOutput": False,
                "jobs": [
                    {
                        "__NAME": "63aa2677-41d6-49d9-8add-2ccc18e8062e",
                        "endpoint": "v3/63aa2677-41d6-49d9-8add-2ccc18e8062e",
                        "method": "POST",
                        "dataType": "63aa2677-41d6-49d9-8add-2ccc18e8062e",
                        "params": {
                            "test": "test"
                        }
                    }
                ]
            },
            "api": {
                "baseUrl": "https://run.mocky.io/"
            }
        }

        configs = configuration.convert_to_v2(config)
        self.assertEqual(configs[0].request_parameters.query_parameters, {})

    def test_query_parameters_kept_in_get_mode(self):
        config = {
            "__SELECTED_JOB": "0",
            "config": {
                "userData": {},
                "outputBucket": "",
                "incrementalOutput": False,
                "jobs": [
                    {
                        "__NAME": "63aa2677-41d6-49d9-8add-2ccc18e8062e",
                        "endpoint": "v3/63aa2677-41d6-49d9-8add-2ccc18e8062e",
                        "method": "GET",
                        "dataType": "63aa2677-41d6-49d9-8add-2ccc18e8062e",
                        "params": {
                            "test": "testValue"
                        }
                    }
                ]
            },
            "api": {
                "baseUrl": "https://run.mocky.io/"
            }
        }

        configs = configuration.convert_to_v2(config)
        self.assertDictEqual(configs[0].request_parameters.query_parameters, {"test": "testValue"})
