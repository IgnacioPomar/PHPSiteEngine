#!/usr/bin/env bash

# Inicializa únicamente los ficheros ausentes del volumen del sitio. De este
# modo un rebuild conserva tanto el sitio como cualquier configuración local.
set -euo pipefail

SITE_ROOT="/var/www/html"
ENGINE_ROOT="/PHPSiteEngine"

sudo mkdir -p "${SITE_ROOT}" "${SITE_ROOT}/cfg" "${SITE_ROOT}/plgs" "${SITE_ROOT}/skins"
sudo chown -R vscode:vscode "${SITE_ROOT}"

# El README usa PHPSiteEngine/... desde la raíz del sitio. El enlace permite
# mantener el motor en su ubicación natural, fuera del volumen del sitio.
if [ ! -e "${SITE_ROOT}/PHPSiteEngine" ]; then
    ln -s "${ENGINE_ROOT}" "${SITE_ROOT}/PHPSiteEngine"
fi

if [ ! -f "${SITE_ROOT}/index.php" ]; then
    cp "${ENGINE_ROOT}/.devcontainer/templates/index.php" "${SITE_ROOT}/index.php"
fi

if [ ! -f "${SITE_ROOT}/SiteConfiguration.php" ]; then
    cp "${ENGINE_ROOT}/.devcontainer/templates/SiteConfiguration.php" "${SITE_ROOT}/SiteConfiguration.php"
fi

if [ ! -f "${SITE_ROOT}/cfg/siteCfg.php" ]; then
    cp "${ENGINE_ROOT}/.devcontainer/templates/siteCfg.php" "${SITE_ROOT}/cfg/siteCfg.php"
fi

PUBLIC_PORT="${SITE_PUBLIC_PORT:-9081}"
HOST_LAN_IP="${HOST_LAN_IP:-}"

echo "Sitio PHPSiteEngine preparado en ${SITE_ROOT}."
echo "Puerto publicado: ${PUBLIC_PORT}"
if [ -n "${HOST_LAN_IP}" ]; then
    echo "URL LAN: http://${HOST_LAN_IP}:${PUBLIC_PORT}/"
    echo "Administración: http://${HOST_LAN_IP}:${PUBLIC_PORT}/SiteConfiguration.php"
else
    echo "IP LAN: no disponible. Configure HOST_LAN_IP en .devcontainer/host.env."
fi
