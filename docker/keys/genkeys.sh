cd keys
echo "creating rootCA"
openssl genrsa -out rootCA.key 4096
openssl req -x509 -new -nodes -key rootCA.key -subj "/C=CZ/ST=CZ/O=authority" -days 1024 -out rootCA.crt
echo "creating server keys"
openssl genrsa -out server.key 2048
openssl req -new  -key server.key -subj "/C=CZ/ST=CZ/O=mytest/CN=server.local" -out server.csr # CN = server.local name of service
openssl x509 -req -in server.csr -CA rootCA.crt -CAkey rootCA.key -CAcreateserial -out server.crt -days 500
echo "creating client keys"
openssl genrsa -out client.key 2048
openssl req -new -key client.key -subj "/C=CZ/ST=CZ/O=mytest/CN=dev" -out client.csr # CN = dev name of service
openssl x509 -req -in client.csr -CA rootCA.crt -CAkey rootCA.key -CAcreateserial -out client.crt -days 500
