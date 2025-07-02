import unittest

from freezegun import freeze_time

from configuration import ConfigHelpers


class TestFunctionTemplates(unittest.TestCase):
    def setUp(self):
        self.config_helpers = ConfigHelpers()

    def execute_function_test(self, function_cfg: dict, expected):
        result = self.config_helpers.perform_custom_function("test", function_cfg, {})
        self.assertEqual(result, expected)

    @freeze_time("2021-01-01")
    def test_date_strtotime(self):
        function_cfg = {
            "function": "date",
            "args": ["Y-m-d", {"function": "strtotime", "args": ["-2 day", {"time": "currentStart"}]}],
        }
        expected = "2020-12-30"
        self.execute_function_test(function_cfg, expected)

    @freeze_time("2021-01-01")
    def test_date_strtotime_timestamp(self):
        function_cfg = {
            "function": "date",
            "args": ["Y-m-d H:i:s", {"function": "strtotime", "args": ["-2 day", {"time": "currentStart"}]}],
        }
        expected = "2020-12-30 00:00:00"
        self.execute_function_test(function_cfg, expected)

    @freeze_time("2021-01-01")
    def test_date_empty_timestamp(self):
        function_cfg = {"function": "date", "args": ["Y-m-d H:i:s"]}
        expected = "2021-01-01 00:00:00"
        self.execute_function_test(function_cfg, expected)

    @freeze_time("2021-01-01")
    def test_relative_iso(self):
        function_cfg = {
            "function": "date",
            "args": ["Y-m-d\\TH:i:sP", {"function": "strtotime", "args": ["-2 day", {"time": "currentStart"}]}],
        }
        expected = "2020-12-30T00:00:00+00:00"
        self.execute_function_test(function_cfg, expected)

    @freeze_time("2021-01-01")
    def test_relative_midnight(self):
        function_cfg = {
            "function": "concat",
            "args": [
                {
                    "function": "date",
                    "args": ["Y-m-d", {"function": "strtotime", "args": ["-1 day", {"time": "currentStart"}]}],
                },
                "T00:00:00.000Z",
            ],
        }
        expected = "2020-12-31T00:00:00.000Z"
        self.execute_function_test(function_cfg, expected)

    @freeze_time("2021-01-01")
    def test_date_in_ymdh(self):
        function_cfg = {"function": "date", "args": ["Y-m-d H:i:s", {"time": "currentStart"}]}
        expected = "2021-01-01 00:00:00"
        self.execute_function_test(function_cfg, expected)

    @freeze_time("2021-01-01")
    def test_previous_start_timestamp(self):
        function_cfg = {"function": "date", "args": ["Y-m-d H:i:s", {"time": "previousStart"}]}
        expected = "2021-01-01 00:00:00"
        self.execute_function_test(function_cfg, expected)

    @freeze_time("2021-01-01")
    def test_previous_start_epoch(self):
        function_cfg = {"time": "previousStart"}
        expected = 1609459200
        self.execute_function_test(function_cfg, expected)

    @freeze_time("2021-01-01")
    def test_current_time_epoch(self):
        function_cfg = {"function": "time"}
        expected = 1609459200
        self.execute_function_test(function_cfg, expected)

    @freeze_time("2021-01-01")
    def test_string_to_epoch(self):
        function_cfg = {"function": "strtotime", "args": ["-7 days", {"time": "currentStart"}]}
        expected = 1608854400
        self.execute_function_test(function_cfg, expected)

    @freeze_time("2021-01-01")
    def test_strtotime_empty_base_time(self):
        function_cfg = {"function": "strtotime", "args": ["now"]}
        expected = 1609459200
        self.execute_function_test(function_cfg, expected)

    @freeze_time("2021-01-01")
    def test_strtotime_empty_base_time_before_days(self):
        function_cfg = {"function": "strtotime", "args": ["-2 days"]}
        expected = 1609286400
        self.execute_function_test(function_cfg, expected)

    def test_concat_ws(self):
        function_cfg = {"function": "implode", "args": [",", ["apples", "oranges", "plums"]]}
        expected = "apples,oranges,plums"
        self.execute_function_test(function_cfg, expected)

    def test_concat(self):
        function_cfg = {"function": "concat", "args": ["Hen", "Or", "Egg"]}
        expected = "HenOrEgg"
        self.execute_function_test(function_cfg, expected)

    @freeze_time("2021-01-01")
    def test_complex_concat(self):
        function_cfg = {
            "function": "concat",
            "args": [
                "=updatedAt",
                ">=",
                {
                    "function": "date",
                    "args": ["d-m-Y", {"function": "strtotime", "args": ["-3 day", {"time": "previousStart"}]}],
                },
            ],
        }
        expected = "=updatedAt>=29-12-2020"
        self.execute_function_test(function_cfg, expected)

    def test_md5(self):
        function_cfg = {"function": "md5", "args": ["NotSoSecret"]}
        expected = "1228d3ff5089f27721f1e0403ad86e73"
        self.execute_function_test(function_cfg, expected)

    def test_sha1(self):
        function_cfg = {"function": "sha1", "args": ["NotSoSecret"]}
        expected = "64d5d2977cc2573afbd187ff5e71d1529fd7f6d8"
        self.execute_function_test(function_cfg, expected)

    def test_base64(self):
        function_cfg = {"function": "base64_encode", "args": ["TeaPot"]}
        expected = "VGVhUG90"
        self.execute_function_test(function_cfg, expected)

    def test_hmac(self):
        function_cfg = {"function": "hash_hmac", "args": ["sha256", "12345abcd5678efgh90ijk", "TeaPot"]}
        expected = "7bd4ec99a609b3a9b1f79bc155037cf70939f6bff50b0012fc49e350586bf554"
        self.execute_function_test(function_cfg, expected)

    def test_sprintf(self):
        function_cfg = {"function": "sprintf", "args": ["Three %s are %.2f %s.", "apples", 0.5, "plums"]}
        expected = "Three apples are 0.50 plums."
        self.execute_function_test(function_cfg, expected)

    def test_ifempty(self):
        function_cfg = {"function": "ifempty", "args": ["", "Banzai"]}
        expected = "Banzai"
        self.execute_function_test(function_cfg, expected)
