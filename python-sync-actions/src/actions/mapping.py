from typing import Optional, Any

from keboola.json_to_csv.analyzer import Analyzer
from keboola.json_to_csv.node import NodeType, Node


class StuctureAnalyzer:

    def __init__(self):
        self.analyzer: Analyzer = Analyzer()

    def parse_row(self, row: dict[str, Any]):
        current_path = []
        for name, value in row.items():
            self.analyzer.analyze_object(current_path, name, value)

    def infer_mapping(self, primary_keys: Optional[list[str]] = None,
                      path_separator: str = '.',
                      max_level: int = 2,
                      ) -> dict:
        """
        Infer first level Generic Extractor mapping from data sample.
        Args:
            primary_keys: optional list of columns to be used as primary keys
            path_separator: JSON path separator to use in the mapping
            max_level: maximum level to flatten results

        Returns:

        """
        return self.__infer_mapping_from_structure_recursive(self.analyzer.node_hierarchy['children'], primary_keys,
                                                             path_separator, max_level)

    def __infer_mapping_from_structure_recursive(self, node_hierarchy: dict[str, Any],
                                                 primary_keys: Optional[list[str]] = None,
                                                 path_separator: str = '.',
                                                 max_level: int = 2, current_mapping: dict = None,
                                                 current_level: int = 0) -> dict:
        """
        Infer first level Generic Extractor mapping from data sample.
        Args:
            node_hierarchy: sample data
            primary_keys: optional list of columns to be used as primary keys

        Returns:

        """
        current_level += 1

        if not current_mapping:
            current_mapping = {}
        for key, value in node_hierarchy.items():
            current_node: Node = value['node']
            path_key = path_separator.join(current_node.path)
            match current_node.data_type:
                case NodeType.SCALAR:
                    if path_key in primary_keys:
                        current_mapping[path_key] = MappingElements.primary_key_column(current_node.header_name)
                    else:
                        current_mapping[path_key] = current_node.header_name

                case NodeType.DICT:
                    if current_level <= max_level:
                        self.__infer_mapping_from_structure_recursive(value['children'], primary_keys,
                                                                      path_separator,
                                                                      max_level, current_mapping,
                                                                      current_level)
                    else:
                        current_mapping[path_key] = MappingElements.force_type_column(current_node.header_name)
                case _:
                    # all other types including unknown map with forceType option
                    current_mapping[path_key] = MappingElements.force_type_column(current_node.header_name)
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
    def force_type_column(column_name: str) -> dict:
        return {
            "type": "column",
            "mapping": {
                "destination": column_name
            },
            "forceType": True
        }


def infer_mapping(data: list[dict], primary_keys: Optional[list[str]] = None) -> dict:
    """
    Infer first level Generic Extractor mapping from data sample.
    Args:
        data: sample data
        primary_keys: optional list of columns to be used as primary keys

    Returns:

    """
    analyzer = StuctureAnalyzer()
    for row in data:
        analyzer.parse_row(row)

    result = analyzer.infer_mapping(primary_keys or [])
    return result
