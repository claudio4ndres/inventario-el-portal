# Servicio cron de respaldo de la base de datos (RNF3).
# Usa la imagen oficial de MySQL solo por su cliente (mysql, mysqldump);
# el servidor no se inicia porque el punto de entrada es el script.
FROM mysql:8.4

RUN command -v gzip >/dev/null || (microdnf install -y gzip && microdnf clean all)

COPY respaldo.sh /usr/local/bin/respaldo.sh
RUN chmod 755 /usr/local/bin/respaldo.sh

ENTRYPOINT ["/usr/local/bin/respaldo.sh"]
