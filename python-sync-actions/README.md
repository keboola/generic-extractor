# Genex Sync actions

# TODO

- Implement all Auth methods in `http_generic.auth.py` as defined in [Confluence](https://keboola.atlassian.net/wiki/spaces/CF/pages/3200745523/Generic+Extractor+UI+Builder#Auth-Methods)
- Implement pagination methods. (not crucial for now)
- Create config structure mapping from the Generic structure / simulated methods
- Implement sync actions


## Dynamic Functions

The application support functions that may be applied on parameters in the configuration to get dynamic values.

Currently these functions work only in the `user_parameters` scope. Place the required function object instead of the
user parameter value.

The function values may refer to another user params using `{"attr": "custom_par"}`

**NOTE:** If you are missing any function let us know or place a PR to
our [repository](https://bitbucket.org/kds_consulting_team/kds-team.wr-generic/src/). It's as simple as adding an
arbitrary method into
the [UserFunctions class](https://bitbucket.org/kds_consulting_team/kds-team.wr-generic/src/master/src/user_functions.py#lines-7)

**Function object**

```json
{
  "function": "string_to_date",
  "args": [
    "yesterday",
    "%Y-%m-%d"
  ]
}
```

#### Function Nesting

Nesting of functions is supported:

```json
{
  "user_parameters": {
    "url": {
      "function": "concat",
      "args": [
        "http://example.com",
        "/test?date=",
        {
          "function": "string_to_date",
          "args": [
            "yesterday",
            "%Y-%m-%d"
          ]
        }
      ]
    }
  }
}

```

#### string_to_date

Function converting string value into a datestring in specified format. The value may be either date in `YYYY-MM-DD`
format, or a relative period e.g. `5 hours ago`, `yesterday`,`3 days ago`, `4 months ago`, `2 years ago`, `today`.

The result is returned as a date string in the specified format, by default `%Y-%m-%d`

The function takes two arguments:

1. [REQ] Date string
2. [OPT] result date format. The format should be defined as in http://strftime.org/

**Example**

```json
{
  "user_parameters": {
    "yesterday_date": {
      "function": "string_to_date",
      "args": [
        "yesterday",
        "%Y-%m-%d"
      ]
    }
  }
}
```

The above value is then available in [supported contexts](/extend/generic-writer/configuration/#referencing-parameters)
as:

```json
"to_date": {"attr": "yesterday_date"}
```

#### concat

Concatenate an array of strings.

The function takes an array of strings to concatenate as an argument

**Example**

```json
{
  "user_parameters": {
    "url": {
      "function": "concat",
      "args": [
        "http://example.com",
        "/test"
      ]
    }
  }
}
```

The above value is then available in supported contexts as:

```json
"url": {"attr": "url"}
```

#### base64_encode

Encodes string in BASE64

**Example**

```json
{
  "user_parameters": {
    "token": {
      "function": "base64_encode",
      "args": [
        "user:pass"
      ]
    }
  }
}
```

The above value is then available in contexts as:

```json
"token": {"attr": "token"}
```

## Development

If required, change local data folder (the `CUSTOM_FOLDER` placeholder) path to your custom path in the docker-compose
file:

```yaml
    volumes:
      - ./:/code
      - ./CUSTOM_FOLDER:/data
```

Clone this repository, init the workspace and run the component with following command:

```
git clone repo_path my-new-component
cd my-new-component
docker-compose build
docker-compose run --rm dev
```

Run the test suite and lint check using this command:

```
docker-compose run --rm test
```

# Integration

For information about deployment and integration with KBC, please refer to
the [deployment section of developers documentation](https://developers.keboola.com/extend/component/deployment/) 