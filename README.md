# Extractor configuration

[![Build Status](https://travis-ci.org/keboola/generic-extractor.svg?branch=master)](https://travis-ci.org/keboola/generic-extractor)


## Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/)

---

# Basics

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/)

- The extractor configuration has 3 parts - `api`, `config` and `cache`
- The `api` section defines the API behavior such as authentication method, pagination, API's base URI etc
- The `config` section should contain actual authentication information (tokens etc), as well as individual endpoints in the `jobs` section
- The `cache` section enables private transparent proxy cache for caching HTTP responses

# API Definition

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/configuration/api/)

## baseUrl

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/configuration/api/#base-url)

The most important part of configuration, the API url (should end with a `/`)
- **Must** be either a string or user function (allows custom domains, see examples)

Example:

```
https://yourDomain.zendesk.com/api/v2/
```   

-- OR --

```
    {
        "api": {
            "function": "concat",
            "args": [
                "https://",
                { "attr": "domain" },
                ".zendesk.com/api/v2/"
            ]
        },
        "config": {
            "domain": "yourDomain"
        }
    }
```

- for *https://yourDomain.zendesk.com/api/v2/*
- uses `config` part, where attribute **domain** would contain `yourDomain`

## retryConfig

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/configuration/api/#retry-configuration)

Set the retry limit, rate limit reset header and HTTP codes to retry if the API returns an error

- **retryConfig.headerName**: (string) `Retry-After`
    - Name of the header with information when can we access the API again
- **retryConfig.httpCodes**: (array) `[500, 502, 503, 504, 408, 420, 429]`
    - HTTP codes on which to retry
- **retryConfig.curlCodes**: (array) `[6, 7, 28, 35, 52]`
    - CURL error codes on which to retry
- **retryConfig.maxRetries**: (int) `10`
    - Maximum retry attempts (useful for exponential backoff, if the limit reset header is not present)

## http.requiredHeaders

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/configuration/api/#required-headers)

- Headers required to be set in the config section
- Should be an array, eg: `App-Key,X-User-Email`
- **http.headers.{Header-Name}** attribute in config section (eg: `http.headers.App-Key`)

    ```
    {
        "api": {
            "http": {
                "requiredHeaders": [
                    "App-Key",
                    "X-User-Email"
                ]
            }
        },
        "config": {
            "http": {
                "headers": {
                    "App-Key": "asdf1234",
                    "X-User-Email": "some@email.com"
                }
            }
        }
    }
    ```

## http.headers.{Header-Name}

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/configuration/api/#required-headers)

- Headers to be sent with all requests from all configurations
- eg: **http.headers.Accept-Encoding**: `gzip`

## http.defaultOptions

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/configuration/api/#required-headers)

- Define the default request options, that will be included in all requests
- eg: **http.defaultOptions.params.queryParameter**: `value`

# Authentication

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/configuration/api/#authentication)

## Methods
### basic

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/configuration/api/authentication/basic/)

- **authentication.type**: `basic`
- use **username** and **password** or **#password** attributes in the config section.
- **password** takes preference over **#password**, if both are set

    ```
    {
        "api": {
            "authentication": {
                "type": "basic"
            }
        },
        "config": {
            "username": "whoever",
            "password": "soSecret"
        }
    }
    ```

### query

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/configuration/api/authentication/query/)

- Supports signature function as a value
- Values should be described in `api` section
- Example bucket attributes:

- **authentication.type**: `query`
- **authentication.query.apiKey**: `{"attr": "apiKey"}`
    - this will look for the *apiKey* query parameter value in the config attribute named *apiKey*
- **authentication.query.sig**: 
    ```
    {
        "function": "md5",
        "args": [
            {
                "function": "concat",
                "args": [
                    {
                        "attr": "apiKey"
                    },
                    {
                        "attr": "secret"
                    },
                    {
                        "function": "time"
                    }
                ]
            }
        ]
    }
    ```   
    - this will generate a *sig* parameter value from MD5 of merged configuration table attributes *apiKey* and *secret*, followed by current *time()* at the time of the request (time() being the PHP function)
    - Allowed functions are listed below in the *User functions* section
    - If you're using any config parameter by using `"attr": "parameterName"`, it has to be identical string to the one in the actual config, including eventual `#` if KBC Docker's encryption is used.

        ```
        {
            "api": {
                "authentication": {
                    "type": "url.query",
                    "query": {
                        "apiKey": {
                            "attr": "apiKey"
                        },
                        "sig": {
                            "function": "md5",
                            "args": [
                                {
                                    "function": "concat",
                                    "args": [
                                        {
                                            "attr": "apiKey"
                                        },
                                        {
                                            "attr": null
                                        },
                                        {
                                            "function": "time"
                                        }
                                    ]
                                }
                            ]
                        }
                    }
                }
            },
            "config": {
                "apiKey": "asdf1234"
            }
        }
        ```

- Data available for building the signature:
    - **attr**: An attribute from `config` (first level only)
    - **query**: A value from a query parameter
        - Ex.: `{ "query": "par1" }` will return `val1` if the query contains `?par1=val1`
    - **request**: Information about the request
        - Available information:
            - `url`
            - `path`
            - `queryString`
            - `method`
            - `hostname`
            - `port`
            - `resource`

### login

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/configuration/api/authentication/login/)

- Log into a web service to obtain a token, which is then used for signing requests

- **authentication.type**: `login`
- **authentication.loginRequest**: Describe the request to log into the service
    - **endpoint**: `string` (required)
    - **params**: `array`
    - **method**: `string`: [`GET`|`POST`|`FORM`]
    - **headers**: `array`
- **authentication.apiRequest**: Defines how to use the result from login
    - **headers**: Use values from the response in request headers
        - `[$headerName => $responsePath]`
    - **query**: Use values from the response in request query
        - `[$queryParameter => $responsePath]`
- **authentication.expires** (optional):
    - If set to an integer, the login action will be performed every `n` seconds, where `n` is the value
    - If set to an array, it *must* contain `response` key with its value containing the path to expiry time in the response
        - `relative` key sets whether the expiry value is relative to current time. False by default.

            ```
            {
                "api": {
                    "authentication": {
                        "type": "login",
                        "loginRequest": {
                            "endpoint": "Security/Login",
                            "headers": {
                                "Content-Type": "application/json"
                            },
                            "method": "POST",
                            "params": {
                                "UserName": {
                                    "attr": "username"
                                },
                                "PassWord": {
                                    "attr": "password"
                                }
                            }
                        },
                        "apiRequest": {
                            "headers": {
                                "Ticket": "Ticket"
                            }
                        }
                    }
                },
                "config": {
                    "username": "whoever",
                    "password": "soSecret"
                }
            }
            ```

### oauth10

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/configuration/api/authentication/oauth10/)

- Use OAuth 1.0 tokens
- Using OAuth in ex-generic-v2 in KBC currently requires the application to be registered under the API's component ID and cannot be configured in Generic extractor itself

This requires the `authorization.oauth_api.credentials` object in configuration to contain `#data`, `appKey` and `#appSecret`, where `#data` **must** contain a JSON encoded object with `oauth_token` and `oauth_token_secret` properties. `appKey` **must** contain the consumer key, and `#appSecret` **must** contain the consumer secret.

Use [Keboola Docker and OAuth API integration](https://developers.keboola.com/extend/common-interface/oauth/) to generate the authorization configuration section.

- **authentication.type**: `oauth10`

Example minimum `config.json`:

```
{
    "authorization": {
        "oauth_api": {
            "credentials": {
                "#data": {"oauth_token":"userToken","oauth_token_secret":"tokenSecret"},
                "appKey": 1234,
                "#appSecret": "asdf"
            }
        }
    },
    "parameters": {
        "api": {
            "authentication": {
                "type": "oauth10"
            }
        }
    }
}
```

### oauth20

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/configuration/api/authentication/oauth20/)

Uses [User functions](#user-functions) to use tokens in headers or query. Instead of `attr` or `time` parameters, you should use `authorization` to access the OAuth data. If the data is a raw token string, use `authorization: data` to access it. If it's a JSON string, use `authentication.format: json` and access its values isong the `.` annotation, like in example below (`authorization: data.access_token`).

The **query** and **request** information can also be used just like in the `querry` authentication method.

- **authentication.type**: `oauth20`

Example config for **Bearer** token use:

```
{
    "authorization": {
        "oauth_api": {
            "credentials": {
                "#data": {"status": "ok","access_token": "testToken"}
            }
        }
    },
    "parameters": {
        "api": {
            "authentication": {
                "type": "oauth20",
                "format": "json",
                "headers": {
                    "Authorization": {
                        "function": "concat",
                        "args": [
                            "Bearer ",
                            {
                                "authorization": "data.access_token"
                            }
                        ]
                    }
                }
            }
        }
    }
}
```

Example for **MAC** authentication:
- Assumes the user token is in the OAuth data JSON in `access_token` key, and MAC secret is in the same JSON in `mac_secret` key.

```
{
    "authorization": {
        "oauth_api": {
            "credentials": {
                "#data": {"status": "ok","access_token": "testToken", "mac_secret": "iAreSoSecret123"},
                "appKey": "clId",
                "#appSecret": "clScrt"
            }
        }
    },
    "parameters": {
        "api": {
            "baseUrl": "http://private-834388-extractormock.apiary-mock.com",
            "authentication": {
                "type": "oauth20",
                "format": "json",
                "headers": {
                    "Authorization": {
                        "function": "concat",
                        "args": [
                            "MAC id=",
                            {
                                "authorization": "data.access_token"
                            },
                            ", ts=",
                            {
                                "authorization": "timestamp"
                            },
                            ", nonce=",
                            {
                                "authorization": "nonce"
                            },
                            ", mac=",
                            {
                                "function": "md5",
                                "args": [
                                    {
                                        "function": "hash_hmac",
                                        "args": [
                                            "sha256",
                                            {
                                                "function": "implode",
                                                "args": [
                                                    "\n",
                                                    [
                                                        {
                                                            "authorization": "timestamp"
                                                        },
                                                        {
                                                            "authorization": "nonce"
                                                        },
                                                        {
                                                            "request": "method"
                                                        },
                                                        {
                                                            "request": "resource"
                                                        },
                                                        {
                                                            "request": "hostname"
                                                        },
                                                        {
                                                            "request": "port"
                                                        },
                                                        "\n"
                                                    ]
                                                ]
                                            },
                                            {
                                                "authorization": "data.mac_secret"
                                            }
                                        ]
                                    }
                                ]
                            }
                        ]
                    }
                }
            }
        }
    }
}
```

# Pagination

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/configuration/api/pagination/)

## Methods
Configured in `api.pagination.method`

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/configuration/api/pagination/#choosing-paging-strategy)

### offset

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/configuration/api/pagination/offset/)

- **pagination.method**: `offset`
- **pagination.limit**: integer
    - If a *limit* is set in configuration's **params** field, it will be overriden by its value
    - If the API limits the results count to a lower value than this setting, the scrolling will stop after first page, as it stops once the results count is lower than configured count
- **pagination.limitParam**(optional)
    - sets which query parameter should contain the limit value (default to `limit`)
- **pagination.offsetParam**(optional)
    - sets which query parameter should contain the offset value (default to `offset`)
        
        ```
            "api": {
                "pagination": {
                    "method": "offset",
                    "limit": 1000,
                    "limitParam": "limit",
                    "offsetParam": "offset"
                }
            }
        ``` 

- **pagination.firstPageParams**(optional)
    - Whether or not include limit and offset params in the first request (default to `true`)
- **pagination.offsetFromJob**(optional)
    - Use offset specified in job config for first request (**false** by default)

    ```
    {
        "api": {
            "pagination": {
                "method": "offset",
                "limit": 1000,
                "offsetFromJob": true
            }
        },
        "config": {
            "jobs": [
                {
                    "endpoint": "resource",
                    "params": {
                        "offset": 100
                    }
                }
            ]
        }
    }
    ```

### response.param

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/configuration/api/pagination/response-param/)

- **pagination.method**: `response.param`
- **pagination.responseParam**:
    - path within response that points to a value used for scrolling
    - pagination ends if the value is empty
- **pagination.queryParam**:
    - request parameter to set to the value from response
- **pagination.includeParams**: `false`
    - whether params from job configuration are used in next page request
- **pagination.scrollRequest**:
    - can be used to override settings (endpoint, method, ...) of the initial request

    ```
        "api": {
            "pagination": {
                "method": "response.param",
                "responseParam": "_scroll_id",
                "queryParam": "scroll_id",
                "scrollRequest": {
                    "endpoint": "_search/scroll",
                    "method": "GET",
                    "params": {
                        "scroll": "1m"
                    }
                }
            }
        }
    ```

### response.url

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/configuration/api/pagination/response-url/)

- **pagination.method**: `response.url`
- **pagination.urlKey**: `next_page`
    - path within response object that points to the URL
    - if value of that key is empty, pagination ends
- **pagination.paramIsQuery**: `false`
    - Enable if the response only contains a query string to use with the same endpoint
- **pagination.includeParams**: `false`
    - whether or not to add "params" from the configuration to the URL's query from response
    - if enabled and the next page URL has the same query parameters as the "params" field, values from the "params" are used

    ```
        "api": {
            "pagination": {
                "method": "response.url",
                "urlKey": "nextPage",
                "includeParams": true
            }
        }
    ```

### pagenum

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/configuration/api/pagination/pagenum/)

simple page number increasing 1 by 1

- **pagination.method**: `pagenum`
- **pagination.pageParam**:(optional) `page` by default
- **pagination.limit**:(optional) integer
    - define the page size
    - if limit is omitted, the pagination will end once an empty page is received. Otherwise it stops once the reply contains less entries than the limit.
- **pagination.limitParam**:(optional)
    - query parameter name to use for *limit*

    ```
        "api": {
            "pagination": {
                "method": "pagenum",
                "pageParam": "page",
                "limit": 500,
                "limitParam": "count"
            }
        }
    ```

- **pagination.firstPage**: (optional) `1` by default. Set the first page number.
- **pagination.firstPageParams**(optional)
    - Whether or not include limit and page params in the first request (default to `true`)

### cursor

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/configuration/api/pagination/cursor/)

Looks within the response **data** for an ID which is then used as a parameter for scrolling.

The intention is to look for identifiers within data and in the next request, use a parameter asking for IDs higher than the highest found (or the opposite, lower than the lowest using the `reverse` parameter)

- **pagination.method**: `cursor`
- **pagination.idKey**: (required)
    - Path within response **data** (ie the array which is parsed into CSV) containing an identifier of each object,
    which is then used in the next request's query
- **pagination.param**: (required)
    - Parameter name in which to pass the value in the next request
- **pagination.increment**: (optional) integer
    - A number by which to increment the highest(/lowest) found value.
    - Can be a negative number, ie if the lowest ID in *data* is `10`, and `increment` is set to `-1`, the next request parameter value will be `9`
- **pagination.reverse**: (optional) bool, `false` by default
    - If set to true, the scroller will look for the **lowest** number instead of the **highest**(default)

- Example:
    ```
        "pagination": {
            "method": "cursor",
            "idKey": "id",
            "param": "max_id",
            "increment": -1,
            "reverse": true
        }
    ```

- Data:
    ```
        "results": [
            {"id": 11},
            {"id": 12}
        ]
    ```

- Request:
    ```
    http://api.example.com/resource?max_id=10
    ```

### multiple

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/configuration/api/pagination/multiple/)

Allows setting scrollers per endpoint.

- **pagination.method**: `multiple`
- **pagination.default**: (optional)
    - Set a **default** scroller to use, if none is specified for the endpoint (if not set, no scrolling is used)
- **pagination.scrollers**: (required)
    - An object where each item represents one of the supported scrollers with their respective configuration
    - The key of each item is then used as identifier for the scroller and must be used in the `scroller` parameter of a job

- Example configuration:

```
    "pagination": {
        "method": "multiple",
        "scrollers": {
            "param_next_cursor": {
                "method": "response.param"
            },
            "param_next_results": {
                "method": "response.param"
            },
            "cursor_timeline": {
                "method": "cursor",
                "idKey": "id",
                "param": "max_id",
                "reverse": true,
                "increment": -1
            }
        }
    }
```

```
    "jobs": [
        {
            "endpoint": "statuses/user_timeline",
            "scroller": "cursor_timeline"
        },
        {
            "endpoint": "search",
            "scroller": "param_next_results",
            "params": {
                "q": "...(twitter search query)"
            }
        }
    ]
```

## Common scrolling parameters
### nextPageFlag

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/configuration/api/pagination/#stopping-strategy)

Looks within responses to find a boolean field determining whether to continue scrolling or not.

Usage:
```
    "pagination": {
        "nextPageFlag": {
            "field": "hasMore",
            "stopOn": false,
            "ifNotSet": false
        }
    }
```

# Config

## Metadata
- The extractor loads start time of its previous execution into its metadata. This can then be used in user functions as `time: previousStart`.
- Current execution start is also available at `time: currentStart`.
- This can be used to create incremental exports with minimal overlap, using for example `[start_time: [time: previousStart], end_time: [time: currentStart]]`
- It is advised to use both `previousStart` and `currentStart` as since>until pair to ensure no gap and no overlap in data.
- Both values are stored as Unix timestamp. `date` function can be used to reformat it.

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/functions/#parameters-context)

## Attributes

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/configuration/config/)

Attributes must be configured accordingly to the `api` configuration (eg *auth*, *pagination*, *http.requiredHeaders*). They are under the `config` section of the configuration. (see example below)

- **outputBucket**: Name of the bucket to store the output data
- **id**: Optional, if **outputBucket** is set. Otherwise the id is used to generate the output bucket name
- **debug**: If set to `true`, the extractor will output detailed information about it's run, including all API requests. **Warning**, this may reveal your tokens or other sensitive data in the events in your project! It is intended only to help solving issues with configuration.
- **userData**: A set of `key:value` pairs that will be added to the `root` of all endpoints' results
    - Example:
    ```
    "config": {
        "userData": {
            "some": "tag",
            "another": "identifier"
        }
    }
    ```

- **incrementalOutput**: (boolean) Whether or not to write the result incrementally
    - Example:
    ```
    "config": {
        "incrementalOutput": true
    }
    ```

## Jobs

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/configuration/config/jobs/)

- Columns:
    - **endpoint** (required): The API endpoint
    - **params**: Query/POST parameters of the api call, JSON encoded
        - Each parameter in the JSON encoded object may either contain a string, eg: `{""start_date"": ""2014-12-26""}`
        - OR contain an user function as described below, for example to load value from parameters:
        ```
        "start_date": {
            "function": "date",
            "args": [
                "Y-m-d+H:i",
                {
                    "function": "strtotime",
                    "args": [
                        {
                            "attr": "job.1.success"
                        }
                    ]
                }
            ]
        }
        ```    
    - **dataType**: Type of data returned by the endpoint. It also describes a table name, where the results will be stored
    - **dataField**: Allows to override which field of the response will be exported.
        - If there's multiple arrays in the response "root" the extractor may not know which array to export and fail
        - If the response is an array, the whole response is used by default
        - If there's no array within the root, the path to response data **must** be specified in *dataField*
        - Can contain a path to nested value, dot separater (eg `result.results.products`)
        - `dataField` can also be an object containing `path`
    - **children**: Array of child jobs that use the jobs' results to iterate
        - The endpoint must use a placeholder enclosed in `{}`
        - The placeholder can be prefixed by a number, that refers to higher level of nesting. By default, data from direct parent are used. The direct parent can be referred as `{id}` or `{1:id}`. A "grandparent" result would then be `{2:id}` etc.
        - Results in the child table will contain column(s) containing parent data used in the placeholder(s), prefixed by **parent_**. For example, if your placeholder is `{ticket_id}`, a column **parent_ticket_id** containing the value of current iteration will be appended to each row.
        - **placeholders** array must define each placeholder. It must be a set of `key: value` pairs, where **key** is the placeholder (eg `"1:id"`) and the value is a path within the response object - if nested, use `.` as a separator.
            - Example:
            ```
            "endpoint": "tickets.json",
            "children": [
                {
                    "endpoint": "tickets/{id}/comments.json",
                    "placeholders": {
                        "id": "id"
                    },
                    "children": [
                        {
                            "endpoint": "tickets/{2:ticket_id}/comments/{comment_id}/details.json",
                            "placeholders": {
                                "comment_id": "id",
                                "2:ticket_id": "id"
                            }
                        }
                    ]
                }
            ]
            ```

            - You can also use an user function on the value from a parent using an object as the placeholder value
            - That object MUST contain a 'path' key that would be the value of the placeholer, and a function. To access the value in the function arguments, use `{"placeholder": "value"}`
                - Example:
                ```
                "placeholders": {
                    "1:id": {
                        "path": "id",
                        "function": "urlencode",
                        "args": [
                            {
                                "placeholder": "value"
                            }
                        ]
                    }
                }
                ```

        - **recursionFilter**:
            - Can contain a value consisting of a name of a field from the parent's response, logical operator and a value to compare against. Supported operators are "**==**", "**<**", "**>**", "**<=**", "**>=**", "**!=**"
            - Example: `type!=employee` or `product.value>150`
            - The filter is whitespace sensitive, therefore `value == 100` will look into `value␣` for a `␣100` value, instead of `value` and `100` as likely desired.
            - Further documentation can be found at https://github.com/keboola/php-filter
    - **method**: GET (default), POST or FORM
    - **responseFilter**: Allows filtering data from API response to leave them from being parsed.
        - Filtered data will be imported as a JSON encoded string.
        - Value of this parameter can be either a string containing path to data to be filtered within response data, or an array of such values.
        - Example:
        ```
        "results": [
            {
                "id": 1,
                "data": "scalar"
            },
            {
                "id": 2,
                "data": {"object": "can't really parse this!"}
            }
        ]
        ```  

        - To be able to work with such response, set `"responseFilter": "data"` - it should be a path within each object of the response array, **not** including the key of the response array
        - To filter values within nested arrays, use `"responseFilter": "data.array[].key"`
        - Example:
        ```
        "results": [
            {
                "id": 1,
                "data": {
                    "array": [
                        {
                            "key": "value"
                        },
                        {
                            "key": {"another": "value"}
                        }
                    ]
                }
            }
        ]
        ```

        - This would be another unparseable object, so the filter above would just convert the `{ 'another': 'value' }` object to a string
        - To filter an entire array, use `array` as the value for *responseFilter*. To filter each array item individually, use `array[]`.
    - **responseFilterDelimiter**: Allows changing delimiter if you need nesting in **responseFilter**, for instance if your data contains keys containing `.`, which is the default delimiter.
        - Example:
        ```
        "results": [
            {
                "data.stuff": {
                    "something": [1,2,3]
                }
            }
        ]
        ```

        - Use `'responseFilter': 'data.stuff/something'` together with `'responseFilterDelimiter': '/'` to filter the array in `something`

## Mappings

Noved to [new docs](https://developers.keboola.com/extend/generic-extractor/configuration/config/mappings/)

`mappings` attribute can be used to force the extractor to map the response into columns in a CSV file as described in the [JSON to CSV Mapper documentation](https://github.com/keboola/php-csvmap).
Each property in the `mappings` object must follow the mapper settings, where the key is the `dataType` of a `job`. Note that if a `dataType` is not set, it is generated from the endpoint and might be confusing if ommited.

If there's no mapping for a `dataType`, the standard JSON parser processes the result.

In a recursive job, the placeholer prepended by `parent_` is available as `type: user` to link the child to a parent. See example below:

Jobs:
```
"jobs": [
{
    "endpoint": "orgs/keboola/repos",
    "dataType": "repos",
    "children": [
    {
        "endpoint": "repos/keboola/{1:name}/issues",
        "placeholders": {
        "1:name": "name"
        },
        "dataType": "issues"
    }
    ]
}
]
```

Mappings (of the child):
```
  "mappings": {
    "issues": {
      "parent_name": {
        "type": "user",
        "mapping": {
          "destination": "repo_name"
        }
      },
      "title": {
        "mapping": {
          "destination": "title"
        }
      },
      "id": {
        "mapping": {
          "destination": "id",
          "primaryKey": true,
          "propertyOrder": 1
        }
      }
    }
  }
```
The `parent_name` is the `parent_` prefix together with the value of placeholder `1:name`.

### Example

```
  "mappings": {
    "get": {
      "id": {
        "mapping": {
          "destination": "id",
          "primaryKey": true
        }
      },
      "status": {
        "mapping": {
          "destination": "st"
        }
      }
    }
  },
  "jobs": [
    {
      "endpoint": "basic",
      "dataType": "get"
    }
  ]
```

# Iterations
The configuration can be run multiple times with some (or all) values in `config` section being overwritten. For example, you can run the same configuration for multiple accounts, overriding values of the authentication settings.

**Warning**:
- If you use `userData` in iterations, make sure they all contain the same set of keys!
- Overriding `incrementalOutput` will only use the setting from the **last** iteration that writes to each `outputBucket`

## Example
This way you can download the same data from two different accounts into a single output table, adding the `owner` column to help you recognize which iteration of the config brought in each row in the result.

```
{
    "api": {
        "baseUrl": "http://example.com/api",
        "authentication": {
            "type": "basic"
        }
    },
    "config": {
        "outputBucket": "bunchOfResults",
        "jobs": [
            {
                "endpoint": "data"
            }
        ]
    },
    "iterations": [
        {
            "username": "chose",
            "password": "potato",
            "userData": {
                "owner": "Chose's results"
            }
        },
        {
            "username": "joann",
            "password": "beer",
            "userData": {
                "owner": "Joann's results"
            }
        }
    ]
}
```

# User functions
Can currently be used in query type authentication or endpoint parameters

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/functions/)

## Allowed functions

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/functions/#supported-functions)

- `md5`: Generate a md5 key from its argument value
- `sha1`: Generate a sha1 key from its argument value
- `time`: Return time from the beginning of the unix epoch in seconds (1.1.1970)
- `date`: Return date in a specified format
- `strtotime`: Convert a date string to number of seconds from the beginning of the unix epoch
- `base64_encode`
- `hash_hmac`: [See PHP documentation](http://php.net/manual/en/function.hash-hmac.php)
- `sprintf`: [See PHP documentation](http://php.net/manual/en/function.sprintf.php)
- `concat`: Concatenate its arguments into a single string
- `implode`: Concatenate an array from the second argument, using glue string from the first arg
- `ifempty`: Return first argument if is not empty, otherwise return second argument

## Syntax

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/functions/#function-contexts)

The function must be specified in JSON format, which may contain one of the following 4 objects:

- **String**: `"something"`
- **Function**: One of the allowed functions above
    - Example (this will return current date in this format: `2014-12-08+09:38`:
        ```
        "function": "date",
        "args":[
            "Y-m-d+H:i"
            ]
        ```

    - Example with a nested function (will return a date in the same format from 3 days ago):
        ```
        "function": "date",
        "args":[
                "Y-m-d+H:i",
                {
                "function": "strtotime",
                "args": [
                    "3 days ago"
                    ]
                }
            ]
        ```

- **Config Attribute**: `"attr": "attributeName"` or `"attr": "nested.attribute.name"`
- **Metadata**: `time: previousStart` or `time: currentStart` - only useable in job params.
- **Query parameter**: **TODO**

# Example configuration

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/running/#running-examples)

```
{
    "parameters": {
        "api": {
            "baseUrl": {
                "function": "concat",
                "args": [
                    "https://",
                    {
                        "attr": "domain"
                    },
                    ".zendesk.com/api/v2/"
                ]
            },
            "authentication": {
                "type": "basic"
            },
            "pagination": {
                "method": "response.url"
            },
            "name": "zendesk"
        },
        "config": {
            "id": "test_docker",
            "domain": "yours",
            "username": "you@wish.com/token",
            "password": "ohIdkSrsly",
            "jobs": [
                {
                    "endpoint": "exports/tickets.json",
                    "params": {
                        "start_time": {
                            "time": "previousStart"
                        },
                        "end_time": {
                            "function": "strtotime",
                            "args": [
                                "2015-07-20 00:00"
                            ]
                        }
                    },
                    "dataType": "tickets_export",
                    "dataField": "",
                    "children": [
                        {
                            "endpoint": "tickets/{id}/comments.json",
                            "recursionFilter": "status!=Deleted",
                            "dataType": "comments",
                            "placeholders": {
                                "id": "id"
                            }
                        }
                    ]
                },
                {
                    "endpoint": "users.json",
                    "params": {},
                    "dataType": "users",
                    "dataField": ""
                },
                {
                    "endpoint": "tickets.json",
                    "params": {},
                    "dataType": "tickets",
                    "dataField": ""
                }
            ]
        }
    }
}
```


# Cache

Use private proxy cache for HTTP responses.
This is useful for local jobs configuration development.

### Enabling cache:
```
"parameters": {
    "api": "...",
    "config": "...",
    "cache": true
}
```
- Caches only responses with one of [`200`, `203`, `300`, `301`, `410`] HTTP Status codes
- Cache *TTL*
    - Count time from `Cache-Control` and `Expires` reponse headers.
    - If counted value is `null`, extractor will use own default value (30 days)
    - Default `ttl` value can be overridden by custom config value (time in seconds)
```
"parameters": {
    "api": "...",
    "config": "...",
    "cache": {
        "ttl": 3600
    }
}
```

# Local development

Moved to [new docs](https://developers.keboola.com/extend/generic-extractor/running/#running-locally)

Best way to create and test new configurations is run extractor in docker container.

## Prerequisites

- Clone this repository `git clone https://github.com/keboola/generic-extractor.git`
- Switch to extractor directory `cd generic-extractor`
- Build container `docker-compose build`
- Install dependencies locally `docker-compose run --rm dev composer install`
- Create data folder for configuration `mkdir data`

## Execution
- Create `config.json` in `data` folder

  Sample configuration which downloads list of Keboola Developers from githhub `data/config.json`:
  ```json
  {
    "parameters": {
      "api": {
        "baseUrl": "https://api.github.com",
        "http": {
          "Accept": "application/json",
          "Content-Type": "application/json;charset=UTF-8"
        }
      },
      "config": {
        "debug": true,
        "jobs": [
          {
            "endpoint": "/orgs/keboola/members",
            "dataType": "members"
          }
        ]
      }
    }
  }
    ```
- Run extraction `docker-compose run --rm dev`
- You will find extracted data in folder `data/out`
- Clear `data/out` by running `docker-compose run --rm dev rm -rf data/out`
- Repeat :)

# Running tests:
```
docker-compose run --rm tests
``` 

or (with local source code and vendor copy)

```
docker-compose run --rm tests-local
``` 

## License

MIT licensed, see [LICENSE](./LICENSE) file.
