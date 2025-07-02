import os
import unittest
from copy import deepcopy
from pathlib import Path

from freezegun import freeze_time

from actions.mapping import infer_mapping, StuctureAnalyzer
from component import Component


class TestCurl(unittest.TestCase):
    SAMPLE_DATA = [
        {
            "id": 123,
            "name": "John Doe",
            "contacts": {
                "email": "john.doe@example.com",
            },
            "array": [1, 2, 3],
        },
        {
            "id": 234,
            "name": "Jane Doe",
            "contacts": {"email": "jane.doe@example.com", "skype": "jane.doe"},
            "array": [1, 2, 3],
        },
    ]

    def setUp(self):
        self.tests_dir = Path(__file__).absolute().parent.joinpath("data_tests").as_posix()

    def _get_test_component(self, test_name):
        test_dir = os.path.join(self.tests_dir, test_name)
        os.environ["KBC_DATADIR"] = test_dir
        return Component()

    def test_nested_levels_pkeys(self):
        # nesting level 0
        expected = {
            "array": {"forceType": True, "mapping": {"destination": "array"}, "type": "column"},
            "contacts": {"forceType": True, "mapping": {"destination": "contacts"}, "type": "column"},
            "id": {"mapping": {"destination": "id", "primaryKey": True}},
            "name": "name",
        }
        res = infer_mapping(self.SAMPLE_DATA, primary_keys=["id"], max_level_nest_level=0)

        self.assertEqual(res, expected)

        # nesting level 1
        expected = {
            "array": {"forceType": True, "mapping": {"destination": "array"}, "type": "column"},
            "contacts.email": "contacts_email",
            "contacts.skype": "contacts_skype",
            "id": {"mapping": {"destination": "id", "primaryKey": True}},
            "name": "name",
        }
        res = infer_mapping(self.SAMPLE_DATA, primary_keys=["id"], max_level_nest_level=1)

        self.assertEqual(res, expected)

    def test_no_pkey(self):
        # nesting level 1
        expected = {
            "array": {"forceType": True, "mapping": {"destination": "array"}, "type": "column"},
            "contacts.email": "contacts_email",
            "contacts.skype": "contacts_skype",
            "id": "id",
            "name": "name",
        }
        res = infer_mapping(self.SAMPLE_DATA, max_level_nest_level=1)

        self.assertEqual(res, expected)

    def test_user_data(self):
        # nesting level 1
        expected = {
            "array": {"forceType": True, "mapping": {"destination": "array"}, "type": "column"},
            "contacts.email": "contacts_email",
            "contacts.skype": "contacts_skype",
            "id": "id",
            "name": "name",
            "date_start": {"mapping": {"destination": "date_start"}, "type": "user"},
        }
        user_data_columns = ["date_start"]
        sample_data = deepcopy(self.SAMPLE_DATA)
        for row in sample_data:
            row["date_start"] = "2021-01-01"

        res = infer_mapping(sample_data, max_level_nest_level=1, user_data_columns=user_data_columns)

        self.assertEqual(res, expected)

    def test_invalid_characters(self):
        data = [
            {
                "$id": 123,
                "name|test": "John Doe",
                "contacts": {
                    "email": "john.doe@example.com",
                },
                "array&&invalid": [1, 2, 3],
            }
        ]
        expected = {
            "$id": "id",
            "array&&invalid": {"forceType": True, "mapping": {"destination": "array__invalid"}, "type": "column"},
            "contacts.email": "contacts_email",
            "name|test": "name_test",
        }
        res = infer_mapping(data, max_level_nest_level=1)

        self.assertEqual(res, expected)

    def test_dedupe_keys(self):
        data = {
            "test_array": "array",
            "array2": {"forceType": True, "mapping": {"destination": "array"}},
            "contacts.email": "contacts_email",
            "contacts.skype": "contacts_email",
            "id": "id",
            "name": "name",
        }
        expected = {
            "array2": {"forceType": True, "mapping": {"destination": "array_1"}},
            "contacts.email": "contacts_email",
            "contacts.skype": "contacts_email_1",
            "id": "id",
            "name": "name",
            "test_array": "array",
        }
        res = StuctureAnalyzer.dedupe_values(data)
        self.assertEqual(res, expected)

    def test_list(self):
        data = {
            "maxResults": 100,
            "startAt": 0,
            "total": 375,
            "values": [
                {"id": "12", "value": {"name": "Max", "age": 25}},
                {"id": "13", "value": {"name": "Tom", "age": 30}},
                {"id": "14", "value": {"name": "John", "age": 35}},
            ],
        }

        expected = {"id": "id", "value.age": "value_age", "value.name": "value_name"}
        res = infer_mapping(data, max_level_nest_level=1)

        self.assertEqual(res, expected)

    @freeze_time("2021-01-01")
    def test_infer_mapping_userdata(self):
        component = self._get_test_component("test_007_infer_mapping_userdata")
        output = component.infer_mapping()
        expected_output = {
            "id": "id",
            "start_date": {"mapping": {"destination": "start_date"}, "type": "user"},
            "status": "status",
        }
        self.assertEqual(output, expected_output)

    def test_infer_mapping_userdata_child(self):
        component = self._get_test_component("test_008_infer_mapping_userdata_child")
        output = component.infer_mapping()
        # child job can't have user data
        expected_output = {"id": "id", "status": "status"}
        self.assertEqual(output, expected_output)

    def test_types(self):
        data = [
            [
                {
                    "id": "asdf",
                    "firstWorkingDay": "2024-07-16",
                    "workingDays": [
                        {"day": "monday"},
                        {"day": "tuesday"},
                        {"day": "wednesday"},
                        {"day": "thursday"},
                        {"day": "friday"},
                    ],
                    "teams": [
                        {
                            "name": "Dream Team",
                        }
                    ],
                },
                {
                    "id": "asdf2",
                    "firstWorkingDay": "2024-07-16",
                    "workingDays": [
                        {"day": "monday"},
                        {"day": "tuesday"},
                        {"day": "wednesday"},
                        {"day": "thursday"},
                        {"day": "friday"},
                    ],
                    "teams": [
                        {
                            "name": "Dream Team",
                        }
                    ],
                },
            ]
        ]

        expected = {
            "firstWorkingDay": "firstWorkingDay",
            "id": "id",
            "teams": {"forceType": True, "mapping": {"destination": "teams"}, "type": "column"},
            "workingDays": {"forceType": True, "mapping": {"destination": "workingDays"}, "type": "column"},
        }
        res = infer_mapping(data, max_level_nest_level=1)

        self.assertEqual(res, expected)
