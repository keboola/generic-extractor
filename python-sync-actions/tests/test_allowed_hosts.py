"""
Tests for allowed hosts validation
"""
import unittest

from keboola.component.exceptions import UserException
from src.component import Component


def create_test_config(base_url: str, allowed_hosts: list = None, endpoint: str = '/path/') -> dict:
    """
    Create test configuration
    Args:
        base_url: Base URL
        allowed_hosts: List of allowed hosts
        endpoint: Endpoint path

    Returns:
        dict: Configuration
    """
    config = {
        'parameters': {
            'api': {
                'baseUrl': base_url,
            },
            'config': {
                'jobs': [{'endpoint': endpoint}],
            },
            '__SELECTED_JOB': '0',
        },
        'image_parameters': {
            'allowed_hosts': [{'host': host} for host in allowed_hosts] if allowed_hosts else []
        }
    }
    return config


class TestAllowedHosts(unittest.TestCase):
    """Tests for allowed hosts validation"""

    def setUp(self):
        """Set up test cases"""
        self.component = Component()

    def test_validate_allowed_hosts_exact_match(self):
        """Test exact match of allowed host"""
        config = create_test_config(
            base_url='https://example.com/api',
            allowed_hosts=['https://example.com/api']
        )
        self.component._validate_allowed_hosts(
            allowed_hosts=config['image_parameters']['allowed_hosts'],
            base_url=config['parameters']['api']['baseUrl'],
            jobs=config['parameters']['config']['jobs']
        )

    def test_validate_allowed_hosts_different_query_string(self):
        """Test different query string"""
        config = create_test_config(
            base_url='https://example.com/api?x=1',
            allowed_hosts=['https://example.com/api']
        )
        self.component._validate_allowed_hosts(
            allowed_hosts=config['image_parameters']['allowed_hosts'],
            base_url=config['parameters']['api']['baseUrl'],
            jobs=config['parameters']['config']['jobs']
        )

    def test_validate_allowed_hosts_different_trailing_slash(self):
        """Test different trailing slash"""
        config = create_test_config(
            base_url='https://example.com/api/',
            allowed_hosts=['https://example.com/api']
        )
        self.component._validate_allowed_hosts(
            allowed_hosts=config['image_parameters']['allowed_hosts'],
            base_url=config['parameters']['api']['baseUrl'],
            jobs=config['parameters']['config']['jobs']
        )

    def test_validate_allowed_hosts_different_protocol(self):
        """Test different protocol"""
        config = create_test_config(
            base_url='https://example.com/api',
            allowed_hosts=['http://example.com/api']
        )
        with self.assertRaises(UserException):
            self.component._validate_allowed_hosts(
                allowed_hosts=config['image_parameters']['allowed_hosts'],
                base_url=config['parameters']['api']['baseUrl'],
                jobs=config['parameters']['config']['jobs']
            )

    def test_validate_allowed_hosts_different_port(self):
        """Test different port"""
        config = create_test_config(
            base_url='https://example.com/api',
            allowed_hosts=['https://example.com:443/api']
        )
        with self.assertRaises(UserException):
            self.component._validate_allowed_hosts(
                allowed_hosts=config['image_parameters']['allowed_hosts'],
                base_url=config['parameters']['api']['baseUrl'],
                jobs=config['parameters']['config']['jobs']
            )

    def test_validate_allowed_hosts_longer_path(self):
        """Test longer path"""
        config = create_test_config(
            base_url='https://example.com/api/resource',
            allowed_hosts=['https://example.com/api']
        )
        self.component._validate_allowed_hosts(
            allowed_hosts=config['image_parameters']['allowed_hosts'],
            base_url=config['parameters']['api']['baseUrl'],
            jobs=config['parameters']['config']['jobs']
        )

    def test_validate_allowed_hosts_multiple_levels(self):
        """Test multiple path levels"""
        config = create_test_config(
            base_url='https://example.com/api/v1/data',
            allowed_hosts=['https://example.com/api']
        )
        self.component._validate_allowed_hosts(
            allowed_hosts=config['image_parameters']['allowed_hosts'],
            base_url=config['parameters']['api']['baseUrl'],
            jobs=config['parameters']['config']['jobs']
        )

    def test_validate_allowed_hosts_trailing_slash_in_whitelist(self):
        """Test trailing slash in whitelist"""
        config = create_test_config(
            base_url='https://example.com/api/v1',
            allowed_hosts=['https://example.com/api/']
        )
        self.component._validate_allowed_hosts(
            allowed_hosts=config['image_parameters']['allowed_hosts'],
            base_url=config['parameters']['api']['baseUrl'],
            jobs=config['parameters']['config']['jobs']
        )

    def test_validate_allowed_hosts_not_real_prefix(self):
        """Test not real prefix"""
        config = create_test_config(
            base_url='https://example.com/api',
            allowed_hosts=['https://example.com/ap']
        )
        with self.assertRaises(UserException):
            self.component._validate_allowed_hosts(
                allowed_hosts=config['image_parameters']['allowed_hosts'],
                base_url=config['parameters']['api']['baseUrl'],
                jobs=config['parameters']['config']['jobs']
            )

    def test_validate_allowed_hosts_domain_vs_subdomain(self):
        """Test domain vs subdomain"""
        config = create_test_config(
            base_url='https://sub.example.com/path',
            allowed_hosts=['https://example.com/']
        )
        with self.assertRaises(UserException):
            self.component._validate_allowed_hosts(
                allowed_hosts=config['image_parameters']['allowed_hosts'],
                base_url=config['parameters']['api']['baseUrl'],
                jobs=config['parameters']['config']['jobs']
            )

    def test_validate_allowed_hosts_ip_address_exact_match(self):
        """Test IP address exact match"""
        config = create_test_config(
            base_url='http://127.0.0.1:8080/api',
            allowed_hosts=['http://127.0.0.1:8080/api']
        )
        self.component._validate_allowed_hosts(
            allowed_hosts=config['image_parameters']['allowed_hosts'],
            base_url=config['parameters']['api']['baseUrl'],
            jobs=config['parameters']['config']['jobs']
        )

    def test_validate_allowed_hosts_ip_address_prefix_match(self):
        """Test IP address prefix match"""
        config = create_test_config(
            base_url='http://127.0.0.1:8080/api/v1/data',
            allowed_hosts=['http://127.0.0.1:8080/api']
        )
        self.component._validate_allowed_hosts(
            allowed_hosts=config['image_parameters']['allowed_hosts'],
            base_url=config['parameters']['api']['baseUrl'],
            jobs=config['parameters']['config']['jobs']
        )

    def test_validate_allowed_hosts_ip_address_different_port(self):
        """Test IP address different port"""
        config = create_test_config(
            base_url='http://127.0.0.1:8000/api',
            allowed_hosts=['http://127.0.0.1:8080/api']
        )
        with self.assertRaises(UserException):
            self.component._validate_allowed_hosts(
                allowed_hosts=config['image_parameters']['allowed_hosts'],
                base_url=config['parameters']['api']['baseUrl'],
                jobs=config['parameters']['config']['jobs']
            )

    def test_validate_allowed_hosts_ip_address_no_port_vs_port(self):
        """Test IP address no port vs port"""
        config = create_test_config(
            base_url='http://127.0.0.1:80/api',
            allowed_hosts=['http://127.0.0.1/api']
        )
        with self.assertRaises(UserException):
            self.component._validate_allowed_hosts(
                allowed_hosts=config['image_parameters']['allowed_hosts'],
                base_url=config['parameters']['api']['baseUrl'],
                jobs=config['parameters']['config']['jobs']
            )

    def test_validate_allowed_hosts_subdomain(self):
        """Test subdomain"""
        config = create_test_config(
            base_url='https://sub.example.com/path1',
            allowed_hosts=['https://sub.example.com/']
        )
        self.component._validate_allowed_hosts(
            allowed_hosts=config['image_parameters']['allowed_hosts'],
            base_url=config['parameters']['api']['baseUrl'],
            jobs=config['parameters']['config']['jobs']
        )

    def test_validate_allowed_hosts_shorter_path_prefix(self):
        """Test shorter path prefix"""
        config = create_test_config(
            base_url='https://sub.domain.com/path/1/2',
            allowed_hosts=['https://sub.domain.com/']
        )
        self.component._validate_allowed_hosts(
            allowed_hosts=config['image_parameters']['allowed_hosts'],
            base_url=config['parameters']['api']['baseUrl'],
            jobs=config['parameters']['config']['jobs']
        )

    def test_validate_allowed_hosts_no_trailing_slash(self):
        """Test no trailing slash"""
        config = create_test_config(
            base_url='https://sub.domain.com/path',
            allowed_hosts=['https://sub.domain.com']
        )
        self.component._validate_allowed_hosts(
            allowed_hosts=config['image_parameters']['allowed_hosts'],
            base_url=config['parameters']['api']['baseUrl'],
            jobs=config['parameters']['config']['jobs']
        )

    def test_validate_allowed_hosts_longer_prefix(self):
        """Test longer prefix"""
        config = create_test_config(
            base_url='https://sub.domain.com/extra/data',
            allowed_hosts=['https://sub.domain.com/extra']
        )
        self.component._validate_allowed_hosts(
            allowed_hosts=config['image_parameters']['allowed_hosts'],
            base_url=config['parameters']['api']['baseUrl'],
            jobs=config['parameters']['config']['jobs']
        )

    def test_validate_allowed_hosts_string_prefix_not_path(self):
        """Test string prefix not path"""
        config = create_test_config(
            base_url='https://sub.domain.com/pathology',
            allowed_hosts=['https://sub.domain.com/path']
        )
        with self.assertRaises(UserException):
            self.component._validate_allowed_hosts(
                allowed_hosts=config['image_parameters']['allowed_hosts'],
                base_url=config['parameters']['api']['baseUrl'],
                jobs=config['parameters']['config']['jobs']
            )

    def test_validate_allowed_hosts_no_whitelist(self):
        """Test no whitelist"""
        config = create_test_config(
            base_url='https://example.com/api',
            allowed_hosts=None
        )
        self.component._validate_allowed_hosts(
            allowed_hosts=config['image_parameters']['allowed_hosts'],
            base_url=config['parameters']['api']['baseUrl'],
            jobs=config['parameters']['config']['jobs']
        )

    def test_validate_allowed_hosts_empty_whitelist(self):
        """Test empty whitelist"""
        config = create_test_config(
            base_url='https://example.com/api',
            allowed_hosts=[]
        )
        self.component._validate_allowed_hosts(
            allowed_hosts=config['image_parameters']['allowed_hosts'],
            base_url=config['parameters']['api']['baseUrl'],
            jobs=config['parameters']['config']['jobs'])

    def test_validate_allowed_multiple_hosts_success(self):
        """Test multiple hosts success"""
        config = create_test_config(
            base_url='https://example.com/api',
            allowed_hosts=['https://example.com/api', 'https://sub.example.com/api']
        )
        self.component._validate_allowed_hosts(
            allowed_hosts=config['image_parameters']['allowed_hosts'],
            base_url=config['parameters']['api']['baseUrl'],
            jobs=config['parameters']['config']['jobs'])

    def test_validate_allowed_multiple_hosts_failure(self):
        """Test multiple hosts failure"""
        config = create_test_config(
            base_url='https://example.com/api',
            allowed_hosts=['https://example.com/api1', 'https://example.com/api2']
        )
        with self.assertRaises(UserException):
            self.component._validate_allowed_hosts(
                allowed_hosts=config['image_parameters']['allowed_hosts'],
                base_url=config['parameters']['api']['baseUrl'],
                jobs=config['parameters']['config']['jobs'])


if __name__ == '__main__':
    unittest.main()
