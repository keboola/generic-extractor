#!/usr/bin/env python3

import json


with open("rootCA.crt") as f:
    ca_cert = f.read()

with open("client.crt") as f:
    client_cert = f.read()

with open("client.key") as f:
    client_key = f.read()

with open("config.json", "w") as f:
    json.dump(
        {
            "api": {
                "baseUrl": "https://server.local/",
                "caCertificate": ca_cert,
                "#clientCertificate": client_cert + client_key,
            }
        },
        f,
        indent=4,
    )
