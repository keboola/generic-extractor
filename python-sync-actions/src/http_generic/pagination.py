class BasePagination:
    def get_page_params(self, paginator_params):
        raise NotImplementedError("Subclasses should implement this method")


class DummyPagination(BasePagination):
    def get_page_params(self, paginator_params):
        return {}


class OffsetPagination(BasePagination):
    def get_page_params(self, paginator_params):
        page_params = {}
        if paginator_params.get("firstPageParams", True):
            page_params[paginator_params.get("offsetParam", "offset")] = paginator_params.get("offset", 0)
            page_params[paginator_params.get("limitParam", "limit")] = paginator_params.get("limit")
        return page_params


class PageNumPagination(BasePagination):
    def get_page_params(self, paginator_params):
        page_params = {}
        if paginator_params.get("firstPageParams"):
            page_params[paginator_params.get("pageParam", "page")] = paginator_params.get("firstPage", 1)
            if paginator_params.get("limit"):
                page_params[paginator_params.get("limitParam", "limit")] = paginator_params.get("limit")
        return page_params


class PaginationBuilder:
    @classmethod
    def get_paginator(cls, pagination):
        """Factory function to create the appropriate paginator configuration."""
        if pagination == "offset":
            return OffsetPagination()
        elif pagination == "pagenum":
            return PageNumPagination()
        else:
            return DummyPagination()
