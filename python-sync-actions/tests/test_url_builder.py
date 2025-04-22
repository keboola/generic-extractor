import unittest

from src.component import Component


class TestUrlBuilder(unittest.TestCase):
    def setUp(self):
        self.component = Component()

    def test_build_urls(self):
        base_url = 'https://api.example.com'
        endpoints = [
            {
                'endpoint': 'users/{id}',
                'params': {'page': 1},
                'placeholders': {'id': 123},
            },
            {
                'endpoint': 'orders',
                'params': {'status': 'completed'},
                'placeholders': {},
            },
        ]

        urls = self.component._build_urls(base_url, endpoints)

        self.assertEqual([
            'https://api.example.com',
            'https://api.example.com/users/123?page=1',
            'https://api.example.com/orders?status=completed',
        ], urls)

    def test_build_urls_with_domain_name(self):
        # Test without trailing slash
        urls = self.component._build_urls('https://example.com', [
            {
                'endpoint': 'users',
                'params': {'page': 1},
                'placeholders': {},
            },
        ])

        self.assertEqual(
            [
                'https://example.com',
                'https://example.com/users?page=1',
            ],
            urls
        )

        # Test with trailing slash
        urls = self.component._build_urls('https://example.com/', [
            {
                'endpoint': 'users',
                'params': {'page': 1},
                'placeholders': {},
            },
        ])

        self.assertEqual(
            [
                'https://example.com',
                'https://example.com/users?page=1',
            ],
            urls
        )

        # Test with subdomain and path
        urls = self.component._build_urls('https://sub.domain.example.com/path/', [
            {
                'endpoint': 'users',
                'params': {'page': 1},
                'placeholders': {},
            },
        ])

        self.assertEqual(
            [
                'https://sub.domain.example.com/path',
                'https://sub.domain.example.com/path/users?page=1',
            ],
            urls
        )

    def test_build_urls_with_port(self):
        base_url = 'https://api.example.com:8080'
        endpoints = [
            {
                'endpoint': 'users/{id}',
                'params': {'page': 1},
                'placeholders': {'id': 123},
            },
        ]

        urls = self.component._build_urls(base_url, endpoints)

        self.assertEqual([
            'https://api.example.com:8080',
            'https://api.example.com:8080/users/123?page=1',
        ], urls)

    def test_build_urls_with_path_in_base_url(self):
        base_url = 'https://api.example.com:8080/v1'
        endpoints = [
            {
                'endpoint': 'users/{id}',
                'params': {'page': 1},
                'placeholders': {'id': 123},
            },
            {
                'endpoint': '/orders',
                'params': {'status': 'completed'},
                'placeholders': {},
            },
        ]

        urls = self.component._build_urls(base_url, endpoints)

        self.assertEqual([
            'https://api.example.com:8080/v1',
            'https://api.example.com:8080/v1/users/123?page=1',
            'https://api.example.com:8080/v1/orders?status=completed',
        ], urls)

    def test_build_urls_with_ip_address(self):
        base_url = 'https://192.168.1.1'
        endpoints = [
            {
                'endpoint': 'api/users',
                'params': {'page': 1},
                'placeholders': {},
            },
        ]

        urls = self.component._build_urls(base_url, endpoints)

        self.assertEqual([
            'https://192.168.1.1',
            'https://192.168.1.1/api/users?page=1',
        ], urls)

    def test_build_urls_with_ip_address_and_port(self):
        base_url = 'https://192.168.1.1:8080'
        endpoints = [
            {
                'endpoint': 'api/users',
                'params': {'page': 1},
                'placeholders': {},
            },
        ]

        urls = self.component._build_urls(base_url, endpoints)

        self.assertEqual([
            'https://192.168.1.1:8080',
            'https://192.168.1.1:8080/api/users?page=1',
        ], urls)

    def test_build_urls_with_localhost(self):
        # Test with localhost IP
        urls = self.component._build_urls('http://127.0.0.1', [
            {
                'endpoint': 'users',
                'params': {'page': 1},
                'placeholders': {},
            },
        ])

        self.assertEqual(
            [
                'http://127.0.0.1',
                'http://127.0.0.1/users?page=1',
            ],
            urls
        )

        # Test with localhost IP and port
        urls = self.component._build_urls('http://127.0.0.1:5000', [
            {
                'endpoint': 'users',
                'params': {'page': 1},
                'placeholders': {},
            },
        ])

        self.assertEqual(
            [
                'http://127.0.0.1:5000',
                'http://127.0.0.1:5000/users?page=1',
            ],
            urls
        )

    def test_build_urls_with_different_protocols(self):
        # Test with HTTP
        urls = self.component._build_urls('http://example.com', [
            {
                'endpoint': 'users',
                'params': {'page': 1},
                'placeholders': {},
            },
        ])

        self.assertEqual(
            [
                'http://example.com',
                'http://example.com/users?page=1',
            ],
            urls
        )

        # Test with HTTPS
        urls = self.component._build_urls('https://example.com', [
            {
                'endpoint': 'users',
                'params': {'page': 1},
                'placeholders': {},
            },
        ])

        self.assertEqual(
            [
                'https://example.com',
                'https://example.com/users?page=1',
            ],
            urls
        )

    def test_build_urls_with_multiple_params(self):
        base_url = 'https://api.example.com'
        endpoints = [
            {
                'endpoint': 'users',
                'params': {
                    'page': 1,
                    'limit': 100,
                    'sort': 'name',
                    'filter': 'active'
                },
                'placeholders': {},
            },
        ]

        urls = self.component._build_urls(base_url, endpoints)

        self.assertEqual([
            'https://api.example.com',
            'https://api.example.com/users?page=1&limit=100&sort=name&filter=active',
        ], urls)


if __name__ == '__main__':
    unittest.main()
