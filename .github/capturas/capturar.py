"""Capturas 16:9 de las pantallas del MPV (HU01–HU17) con los datos de demostración.

Rama temporal capturas/v1: no se integra a develop ni a main.
"""
import re
import sys
from pathlib import Path

from playwright.sync_api import sync_playwright

BASE = "http://127.0.0.1:8000"
OUT = Path("capturas/v1")
OUT.mkdir(parents=True, exist_ok=True)
VISTA = {"width": 1366, "height": 768}
errores = []


def login(page, correo):
    page.goto(f"{BASE}/login")
    page.fill("#email", correo)
    page.fill("#password", "ElPortal2026")
    page.click("form button.btn-success")
    page.wait_for_load_state("networkidle")


def foto(page, nombre, url=None):
    try:
        if url:
            page.goto(f"{BASE}{url}")
            page.wait_for_load_state("networkidle")
        page.screenshot(path=str(OUT / f"{nombre}.png"))
        print("ok", nombre)
    except Exception as e:  # una pantalla fallida no detiene el resto
        errores.append(f"{nombre}: {e}")
        print("ERROR", nombre, e)


def paso(nombre, fn):
    try:
        fn()
    except Exception as e:
        errores.append(f"{nombre}: {e}")
        print("ERROR", nombre, e)


with sync_playwright() as p:
    nav = p.chromium.launch()

    # Invitado
    ctx = nav.new_context(viewport=VISTA, locale="es-CL")
    foto(ctx.new_page(), "hu01-login", "/login")
    ctx.close()

    # Administrador
    ctx = nav.new_context(viewport=VISTA, locale="es-CL", accept_downloads=True)
    page = ctx.new_page()
    page.on("dialog", lambda d: d.accept())
    login(page, "admin@elportal.test")
    for nombre, url in [
        ("hu01-panel", "/"),
        ("hu02-productos", "/productos"),
        ("hu02-producto-form", "/productos/crear"),
        ("hu06-alertas", "/alertas"),
        ("hu07-bitacora", "/movimientos"),
        ("hu08-usuarios", "/usuarios"),
        ("hu09-proveedores", "/proveedores"),
        ("hu11-ordenes-compra", "/ordenes-compra"),
        ("hu12-lotes-por-vencer", "/lotes/por-vencer"),
        ("hu13-merma-form", "/mermas/crear"),
        ("hu13-mermas", "/mermas"),
        ("hu14-conteos", "/conteos"),
        ("hu14-planilla", "/conteos/crear?clase=A"),
        ("hu15-indicadores", "/indicadores"),
        ("hu16-importar", "/productos/importar"),
        ("hu03-recepcion", "/recepciones/crear"),
        ("hu04-venta", "/ventas/crear"),
        ("hu04-ventas", "/ventas"),
    ]:
        foto(page, nombre, url)

    def detalle(lista, patron, nombre):
        page.goto(f"{BASE}{lista}")
        page.wait_for_load_state("networkidle")
        enlaces = [a for a in page.locator("main a").element_handles()
                   if re.search(patron, a.get_attribute("href") or "")]
        enlaces[0].click()
        page.wait_for_load_state("networkidle")
        foto(page, nombre)

    paso("hu04-venta-detalle", lambda: detalle("/ventas", r"/ventas/\d+$", "hu04-venta-detalle"))
    paso("hu11-orden-detalle", lambda: detalle("/ordenes-compra", r"/ordenes-compra/\d+$", "hu11-orden-detalle"))

    # HU16: plantilla descargada -> vista previa
    def importar():
        page.goto(f"{BASE}/productos/importar")
        page.wait_for_load_state("networkidle")
        with page.expect_download() as d:
            page.locator("a[href*='plantilla']").first.click()
        ruta = d.value.path()
        page.set_input_files("input[type=file]", ruta)
        page.locator("form:has(input[type=file]) button").last.click()
        page.wait_for_load_state("networkidle")
        foto(page, "hu16-vista-previa")
    paso("hu16-vista-previa", importar)

    # HU10 y HU17: botones de recálculo en productos
    for nombre, texto in [("hu10-recalcular-reorden", "Recalcular punto"), ("hu17-recalcular-abc", "Recalcular ABC")]:
        def recalcular(texto=texto, nombre=nombre):
            page.goto(f"{BASE}/productos")
            page.wait_for_load_state("networkidle")
            page.get_by_role("button", name=re.compile(texto, re.I)).first.click()
            page.wait_for_load_state("networkidle")
            foto(page, nombre)
        paso(nombre, recalcular)
    ctx.close()

    # Vendedor y bodeguero: lo que ve cada rol
    for correo, nombre in [("caja@elportal.test", "rol-vendedor-panel"), ("bodega@elportal.test", "rol-bodeguero-panel")]:
        ctx = nav.new_context(viewport=VISTA, locale="es-CL")
        page = ctx.new_page()
        login(page, correo)
        foto(page, nombre, "/")
        ctx.close()
    nav.close()

print(f"{len(list(OUT.glob('*.png')))} capturas; errores: {len(errores)}")
for e in errores:
    print("  -", e)
sys.exit(1 if len(list(OUT.glob('*.png'))) < 20 else 0)
