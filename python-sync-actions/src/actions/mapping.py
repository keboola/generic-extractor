from typing import Optional, Any

from keboola.component import UserException
from keboola.json_to_csv.analyzer import Analyzer
from keboola.json_to_csv.node import NodeType, Node
from keboola.utils.header_normalizer import DefaultHeaderNormalizer


class HeaderNormalizer(DefaultHeaderNormalizer):

    def _normalize_column_name(self, column_name: str) -> str:
        # Your implementation here

        column_name = self._replace_whitespace(column_name)
        column_name = self._replace_forbidden(column_name)
        if column_name.startswith('_'):
            column_name = column_name[1:]

        return column_name


class StuctureAnalyzer:

    def __init__(self):
        self.analyzer: Analyzer = Analyzer()
        self.header_normalizer = HeaderNormalizer(forbidden_sub='_')

    def parse_row(self, row: dict[str, Any]):
        current_path = []
        for name, value in row.items():
            self.analyzer.analyze_object(current_path, name, value)

    def infer_mapping(self,
                      primary_keys: Optional[list[str]] = None,
                      parent_pkeys: Optional[list[str]] = None,
                      path_separator: str = '.',
                      max_level: int = 2
                      ) -> dict:
        """
        Infer first level Generic Extractor mapping from data sample.
        Args:
            primary_keys: optional list of columns to be used as primary keys
            parent_pkeys: optional list of columns to be used as parent primary keys
            path_separator: JSON path separator to use in the mapping
            max_level: maximum level to flatten results

        Returns:

        """
        result_mapping = self.__infer_mapping_from_structure_recursive(self.analyzer.node_hierarchy['children'],
                                                                       primary_keys,
                                                                       path_separator, max_level)

        if parent_pkeys:
            for key in parent_pkeys:
                if key in result_mapping:
                    raise UserException(f"Parent {key} is already in the mapping, "
                                        f"please change the placeholder name")
                result_mapping[key] = MappingElements.parent_primary_key_column(key)

        return self.dedupe_values(result_mapping)

    @staticmethod
    def dedupe_values(mapping: dict) -> dict:
        """
        Dedupe values in mapping by adding suffixes.
        Args:
            mapping: mapping to dedupe
        """
        seen = {}
        for key, value in mapping.items():
            simple_mapping = True
            col_name = value
            if isinstance(value, dict):
                col_name = value['mapping']['destination']
                simple_mapping = False

            if col_name in seen.keys():
                seen[col_name] += 1
                if simple_mapping:
                    mapping[key] = f"{col_name}_{seen[col_name]}"
                else:
                    mapping[key]['mapping']['destination'] = f"{col_name}_{seen[col_name]}"
            else:
                seen[col_name] = 0
        return mapping

    def __infer_mapping_from_structure_recursive(self, node_hierarchy: dict[str, Any],
                                                 primary_keys: Optional[list[str]] = None,
                                                 path_separator: str = '.',
                                                 max_level: int = 2, current_mapping: dict = None,
                                                 current_level: int = 0) -> dict:
        """
        Infer first level Generic Extractor mapping from data sample.
        Args:
            node_hierarchy: sample data
            primary_keys: optional list of columns to be used as primary keys=

        Returns:

        """
        current_level += 1

        if not current_mapping:
            current_mapping = {}
        for key, value in node_hierarchy.items():
            current_node: Node = value['node']
            path_key = path_separator.join(current_node.path)
            normalized_header_name = self.header_normalizer._normalize_column_name(current_node.header_name)  # noqa
            match current_node.data_type:
                case NodeType.SCALAR:
                    if path_key in primary_keys:
                        current_mapping[path_key] = MappingElements.primary_key_column(normalized_header_name)
                    else:
                        current_mapping[path_key] = normalized_header_name

                case NodeType.DICT:
                    if current_level <= max_level:
                        self.__infer_mapping_from_structure_recursive(value['children'], primary_keys,
                                                                      path_separator,
                                                                      max_level, current_mapping,
                                                                      current_level)
                    else:
                        current_mapping[path_key] = MappingElements.force_type_column(normalized_header_name)
                case _:
                    # all other types including unknown map with forceType option
                    current_mapping[path_key] = MappingElements.force_type_column(normalized_header_name)
        return current_mapping


class MappingElements:
    @staticmethod
    def primary_key_column(column_name: str) -> dict:
        return {
            "mapping": {
                "destination": column_name,
                "primaryKey": True
            }
        }

    @staticmethod
    def parent_primary_key_column(column_name: str) -> dict:
        return {
            "type": "user",
            "mapping": {
                "destination": column_name,
                "primaryKey": True
            }
        }

    @staticmethod
    def force_type_column(column_name: str) -> dict:
        return {
            "type": "column",
            "mapping": {
                "destination": column_name
            },
            "forceType": True
        }


def infer_mapping(data: list[dict],
                  primary_keys: Optional[list[str]] = None,
                  parent_pkeys: Optional[list[str]] = None,
                  path_separator: str = '.',
                  max_level_nest_level: int = 2) -> dict:
    """
    Infer first level Generic Extractor mapping from data sample.
    Args:
        data: sample data
        primary_keys: optional list of columns to be used as primary keys
        parent_pkeys: optional list of columns to be used as parent primary keys
        path_separator: JSON path separator to use in the mapping
        max_level_nest_level: maximum level to flatten results

    Returns:

    """
    analyzer = StuctureAnalyzer()
    for row in data:
        analyzer.parse_row(row)

    result = analyzer.infer_mapping(primary_keys or [], parent_pkeys or [],
                                    path_separator=path_separator,
                                    max_level=max_level_nest_level)
    return result


def get_primary_key_columns(mapping: dict) -> list[str]:
    """
    Get primary key columns from mapping
    Args:
        mapping:

    Returns:

    """
    return [key for key, value in mapping.items() if
            isinstance(value, dict) and value.get('mapping', {}).get('primaryKey')]
