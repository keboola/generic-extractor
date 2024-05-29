from collections import namedtuple
from itertools import product
from typing import List, Dict, Union, Any


class UserException(Exception):
    pass


class NoDataFoundException(Exception):
    pass


Placeholder = namedtuple('Placeholder', ['placeholder', 'json_path', 'value'])


class PlaceholdersUtils:

    @staticmethod
    def get_params_for_child_jobs(placeholders: Dict[str, Any], parent_results: List[Dict[str, Any]],
                                  parent_params: Dict[str, Any]) -> List[Dict[str, Any]]:
        params = {}
        for placeholder, field in placeholders.items():
            params[placeholder] = PlaceholdersUtils.get_placeholder(placeholder, field, parent_results)

        # Add parent params as well (for 'tagging' child-parent data)
        # Same placeholder in deeper nesting replaces parent value
        params = {**parent_params, **params}

        # Create all combinations if there are some parameter values as array.
        # Each combination will be one child job.
        return PlaceholdersUtils.get_params_per_child_job(params)

    @staticmethod
    def get_placeholder(placeholder: str, field: Union[str, Dict[str, Any]],
                        parent_results: List[Dict[str, Any]]) -> Dict[str, Any]:
        # Determine the level based on the presence of ':' in the placeholder name
        level = 0 if ':' not in placeholder else int(placeholder.split(':')[0]) - 1

        # Check function (defined as dict)
        if not isinstance(field, str):
            if 'path' not in field:
                raise UserException(f"The path for placeholder '{placeholder}' must be a string value"
                                    f"or an object containing 'path' and 'function'.")
            fn = field.copy()
            field = fn.pop('path')

        # Get value
        value = PlaceholdersUtils.get_placeholder_value(str(field), parent_results, level, placeholder)

        # Run function if provided
        if 'fn' in locals():
            # Example function to be replaced by actual implementation
            value = value

        return {
            'placeholder': placeholder,
            'json_path': field,
            'value': value
        }

    @staticmethod
    def get_placeholder_value(field: str, parent_results: List[Dict[str, Any]], level: int, placeholder: str) -> Any:
        try:
            if level >= len(parent_results):
                max_level = 0 if not parent_results else len(parent_results)
                raise UserException(f'Level {level + 1} not found in parent results! Maximum level: {max_level}')

            # Implement get_data_from_path to fetch data using a dot notation
            data = get_data_from_path(field, parent_results[level])

            return data

        except NoDataFoundException:
            raise UserException(
                f"No value found for placeholder {placeholder} in parent result. (level: {level + 1})",
                None, None, {'parents': parent_results}
            )

    @staticmethod
    def get_params_per_child_job(params: Dict[str, Placeholder]) -> List[Dict[str, Any]]:
        # Flatten parameters to a list of lists
        flattened = {}
        for placeholder_name, placeholder in params.items():
            if isinstance(placeholder['value'], list):
                flattened[placeholder_name] = [
                    {'placeholder': placeholder_name, 'json_path': placeholder['json_path'], 'value': value}
                    for value in placeholder['value']
                ]
            else:
                flattened[placeholder_name] = [placeholder]

        # Cartesian product to get all combinations
        return PlaceholdersUtils.cartesian(flattened)

    @staticmethod
    def cartesian(input_data: Dict[str, List[Dict[str, Any]]]) -> List[Dict[str, Any]]:
        # Generate the Cartesian product of input lists
        keys, values = zip(*input_data.items())
        product_list = [dict(zip(keys, combination)) for combination in product(*values)]

        return product_list


def get_data_from_path(json_path: str, data: Dict[str, Any], separator: str = '.', strict: bool = True) -> Any:
    """Mock function to fetch data using a dot-separated path notation. Replace with actual implementation."""
    keys = json_path.split(separator)
    for key in keys:
        if key not in data:
            if strict:
                raise NoDataFoundException(f"Key '{key}' not found in login data.")
            return None
        data = data[key]
    return data
