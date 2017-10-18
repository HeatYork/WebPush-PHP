openssl pkcs12 -clcerts -nokeys -out $1cert-cert.pem -in $1cert.p12 -passin pass:mypassword
openssl pkcs12 -nocerts -out $1cert-key.pem -in $1cert-key.p12 -passin pass:mypassword -passout pass:mypassword
openssl rsa -in $1cert-key.pem -out $1cert-key-nokey.pem -passin pass:mypassword
cat $1cert-cert.pem $1cert-key-nokey.pem > $1cert.pem
