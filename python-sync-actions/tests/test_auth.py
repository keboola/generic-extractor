import unittest

from http_generic.auth import Login


class TestLoginAuth(unittest.TestCase):

    def test_response_placeholders_simple(self):
        data = {"some_key": "some value",
                "nested": {"token": {"response": "accesstoken"}}}
        result = Login._retrieve_response_placeholders(data, separator='.')
        expected = {'nested.token': 'accesstoken'}
        self.assertEqual(result, expected)

    def test_response_placeholders_multiple(self):
        data = {"some_key": "some value",
                "first": {"response": "first_response"},
                "nested": {"token": {"response": "accesstoken"}}}
        result = Login._retrieve_response_placeholders(data, separator='_')
        expected = {'first': 'first_response', 'first_nested_token': 'accesstoken'}
        self.assertEqual(result, expected)
