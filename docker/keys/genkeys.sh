echo "creating rootCA"
openssl genrsa -out rootCA.key 4096
openssl req -x509 -new -nodes -key rootCA.key -subj "/C=CZ/ST=CZ/O=authority" -days 1024 -out rootCA.crt

echo "creating server keys"
openssl genrsa -out server.key 2048
# SAN is required as it is the main place where modern clients check host name (in fact CN can be ignored -- and is by e.g. chrome or requests)
openssl req -new  -key server.key -subj "/C=CZ/ST=CZ/O=mytest/CN=server.local" -addext "subjectAltName=DNS:server.local" -out server.csr
# Extensions such as SAN are not coppied by default from CSR when creating the certificate, -copy_extensions is required (semi-recent addition to OpenSSL)
openssl x509 -req -in server.csr -CA rootCA.crt -CAkey rootCA.key -CAcreateserial -out server.crt -days 500 -copy_extensions copy

echo "creating client keys"
openssl genrsa -out client.key 2048
openssl req -new -key client.key -subj "/C=CZ/ST=CZ/O=mytest/CN=client.local" -addext "subjectAltName=DNS:client.local" -out client.csr
openssl x509 -req -in client.csr -CA rootCA.crt -CAkey rootCA.key -CAcreateserial -out client.crt -days 500 -copy_extensions copy

python3 keys-to-config-json.py
