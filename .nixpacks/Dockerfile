FROM ghcr.io/railwayapp/nixpacks:ubuntu-1745885067

ENTRYPOINT ["/bin/bash", "-l", "-c"]
WORKDIR /app/


COPY .nixpacks/nixpkgs-e24b4c09e963677b1beea49d411cd315a024ad3a.nix .nixpacks/nixpkgs-e24b4c09e963677b1beea49d411cd315a024ad3a.nix
RUN nix-env -if .nixpacks/nixpkgs-e24b4c09e963677b1beea49d411cd315a024ad3a.nix && nix-collect-garbage -d

COPY .nixpacks/assets /assets/
ARG GIT_SHA NIXPACKS_METADATA PORT
ENV GIT_SHA=$GIT_SHA NIXPACKS_METADATA=$NIXPACKS_METADATA PORT=$PORT

# setup phase
# noop

# install phase
COPY . /app/.
RUN  mkdir -p /var/log/nginx && mkdir -p /var/cache/nginx




# start
COPY . /app

# O PHP-FPM roda como "nobody": a pasta de fotos de perfil precisa ser dele
RUN mkdir -p /app/assets/uploads/perfil /app/assets/uploads/servicos /app/assets/uploads/catalogo && chown -R nobody /app/assets/uploads && chmod -R u+rwX /app/assets/uploads

CMD ["mkdir -p /app/assets/uploads/perfil /app/assets/uploads/servicos /app/assets/uploads/catalogo && chown -R nobody /app/assets/uploads && node /assets/scripts/prestart.mjs /assets/nginx.template.conf /nginx.conf && (command -v php >/dev/null 2>&1 && (while true; do TZ=America/Sao_Paulo php /app/Financeiro/cron/agendador.php; r=$?; [ $r -eq 0 -o $r -eq 3 ] && break; sleep 10; done >> /proc/1/fd/1 2>&1 &) || echo 'AVISO: php CLI nao encontrado; relatorios automaticos desativados') && (php-fpm -y /assets/php-fpm.conf & nginx -c /nginx.conf)"]

