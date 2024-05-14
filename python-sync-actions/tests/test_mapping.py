import unittest

from actions.mapping import infer_mapping, StuctureAnalyzer


class TestCurl(unittest.TestCase):
    SAMPLE_DATA = [
        {
            "id": 123,
            "name": "John Doe",
            "contacts": {
                "email": "john.doe@example.com",
            },
            "array": [1, 2, 3]
        },
        {
            "id": 234,
            "name": "Jane Doe",
            "contacts": {
                "email": "jane.doe@example.com",
                "skype": "jane.doe"
            },
            "array": [1, 2, 3]
        }
    ]

    def test_nested_levels_pkeys(self):
        # nesting level 0
        expected = {'array': {'forceType': True, 'mapping': {'destination': 'array'}, 'type': 'column'},
                    'contacts': {'forceType': True, 'mapping': {'destination': 'contacts'}, 'type': 'column'},
                    'id': {'mapping': {'destination': 'id', 'primaryKey': True}}, 'name': 'name'}
        res = infer_mapping(self.SAMPLE_DATA, primary_keys=['id'], max_level_nest_level=0)

        self.assertEqual(res, expected)

        # nesting level 1
        expected = {'array': {'forceType': True, 'mapping': {'destination': 'array'}, 'type': 'column'},
                    'contacts.email': 'contacts_email', 'contacts.skype': 'contacts_skype',
                    'id': {'mapping': {'destination': 'id', 'primaryKey': True}}, 'name': 'name'}
        res = infer_mapping(self.SAMPLE_DATA, primary_keys=['id'], max_level_nest_level=1)

        self.assertEqual(res, expected)

    def test_no_pkey(self):
        # nesting level 1
        expected = {'array': {'forceType': True, 'mapping': {'destination': 'array'}, 'type': 'column'},
                    'contacts.email': 'contacts_email', 'contacts.skype': 'contacts_skype',
                    'id': 'id', 'name': 'name'}
        res = infer_mapping(self.SAMPLE_DATA, max_level_nest_level=1)

        self.assertEqual(res, expected)

    def test_invalid_characters(self):
        data = [{
            "$id": 123,
            "name|test": "John Doe",
            "contacts": {
                "email": "john.doe@example.com",
            },
            "array&&invalid": [1, 2, 3]
        }]
        expected = {'$id': 'id', 'array&&invalid': {'forceType': True, 'mapping': {'destination': 'array__invalid'},
                                                    'type': 'column'}, 'contacts.email': 'contacts_email',
                    'name|test': 'name_test'}
        res = infer_mapping(data, max_level_nest_level=1)

        self.assertEqual(res, expected)

    def test_dedupe_keys(self):
        data = {'test_array': 'array', 'array2': {'forceType': True, 'mapping': {'destination': 'array'}},
                'contacts.email': 'contacts_email', 'contacts.skype': 'contacts_email',
                'id': 'id', 'name': 'name'}
        expected = {'array2': {'forceType': True, 'mapping': {'destination': 'array_1'}},
                    'contacts.email': 'contacts_email', 'contacts.skype': 'contacts_email_1', 'id': 'id',
                    'name': 'name', 'test_array': 'array'}
        res = StuctureAnalyzer.dedupe_values(data)
        self.assertEqual(res, expected)
