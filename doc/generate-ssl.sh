#!/bin/bash
set -e

# FROM: https://gist.github.com/komuw/076231fd9b10bb73e40f
export TARGET_DIR="examples/142-https-client-cert/https"
export DAYS=50000

# Cleanup
rm -rf $TARGET_DIR/client_ca
rm -rf $TARGET_DIR/ca
rm -rf $TARGET_DIR/certs
rm -rf $TARGET_DIR/client_certs
cd $TARGET_DIR

mkdir client_ca
mkdir ca
mkdir certs
mkdir client_certs

### SERVER CA ###
cd ca
# Create the CA Key and Certificate for signing Server Certs
openssl genrsa -out rootCA.key 4096
openssl req -subj "/CN=mock-server-https-client-cert-proxy-CA" -new -x509 -days $DAYS -key rootCA.key -out rootCA.crt

### CLIENT CA ###

cd ../client_ca
# Create the CA Key and Certificate for signing Client Certs
openssl genrsa -out rootCA.key 4096
openssl req -subj "/CN=mock-server-https-client-cert-proxy-CA" -new -x509 -days $DAYS -key rootCA.key -out rootCA.crt

### SERVER CERTS ###

cd ../certs
# Create the Server Key, CSR, and Certificate
openssl genrsa -out key.pem 4096
openssl req -subj "/CN=mock-server-https-client-cert-proxy" -new -key key.pem -out csr.pem

cd ..
# We're self signing our own server cert here.  This is a no-no in production.
openssl x509 -req -days $DAYS -in certs/csr.pem -CA ca/rootCA.crt -CAkey ca/rootCA.key -set_serial 01 -out certs/cert.pem

### CLIENT CERTS ###

cd client_certs
# Create the Client Key and CSR
openssl genrsa -out client_key.pem 4096
openssl req -subj "/CN=mock-server-https-client-cert-proxy" -new -key client_key.pem -out client_csr.pem

cd ..
# Sign the client certificate with our CA cert.  Unlike signing our own server cert, this is what we want to do.
# Serial should be different from the server one, otherwise curl will return NSS error -8054
openssl x509 -req -days $DAYS -in client_certs/client_csr.pem -CA client_ca/rootCA.crt -CAkey client_ca/rootCA.key -set_serial 01 -out client_certs/client_cert.pem

### VERIFY CERTIFICATES

# Verify Server Certificate
openssl verify -purpose sslserver -CAfile ca/rootCA.crt certs/cert.pem

# Verify Client Certificate
openssl verify -purpose sslclient -CAfile client_ca/rootCA.crt client_certs/client_cert.pem