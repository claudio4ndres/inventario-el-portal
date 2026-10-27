"""Toma capturas de las pantallas del sistema con los datos de demostración (rama temporal capturas/pantallas)."""
from pathlib import Path
from playwright.sync_api import sync_playwright

BASE = "http://127.0.0.1:8000"
OUT = Path("capturas")
OUT.mkdir(exist_ok=True)


def login(page, correo):
    page.goto(f"{BASE}/login")
    page.fill("#email", correo)
    page.fill("#password", "ElPortal2026")
    page.click("form button.btn-success")
    page.wait_for_load_state("networkidle")


def foto(page, nombre, url=None):
    if url:
        page.goto(f"{BASE}{url}")
        page.wait_for_load_state("networkidle")
    page.screenshot(path=str(OUT / f"{nombre}.png"), full_page=True)
    print("ok", nombre)


with sync_playwright() as p:
    nav = p.chromium.launch()

    # --- HU14: conteo cíclico (bodeguero) ---
    ctx = nav.new_context(viewport={"width": 1366, "height": 800}, locale="es-CL")
    page = ctx.new_page()
    page.on("dialog", lambda d: d.accept())
    login(page, "bodega@elportal.test")
    foto(page, "hu14-01-conteos-programacion", "/conteos")
    page.goto(f"{BASE}/conteos/crear?clase=B")
    page.wait_for_load_state("networkidle")
    filas = page.locator("tr.fila-conteo")
    for i in range(filas.count()):
        fila = filas.nth(i)
        sistema = int(fila.get_attribute("data-sistema"))
        nombre = fila.locator("td").first.inner_text()
        valor = sistema - 2 if "Yogur" in nombre else sistema
        fila.locator("input.cantidad").fill(str(valor))
    foto(page, "hu14-02-conteo-planilla-con-diferencia")
    page.click("main form button.btn-success")
    page.wait_for_load_state("networkidle")
    foto(page, "hu14-03-conteo-error-sin-motivo")
    for i in range(filas.count()):
        fila = filas.nth(i)
        if "Yogur" in fila.locator("td").first.inner_text():
            fila.locator("input[name$='[motivo]']").fill("Vencidos retirados sin registrar merma")
    page.click("main form button.btn-success")
    page.wait_for_load_state("networkidle")
    foto(page, "hu14-04-conteo-detalle-exactitud")
    foto(page, "hu14-05-conteos-programacion-actualizada", "/conteos")
    ctx.close()

    # --- Administrador: bitácora y resto de pantallas ---
    ctx = nav.new_context(viewport={"width": 1366, "height": 800}, locale="es-CL")
    page = ctx.new_page()
    login(page, "admin@elportal.test")
    foto(page, "hu14-06-bitacora-ajustes", "/movimientos?tipo=AJUSTE")
    for nombre, url in [
        ("hu01-02-panel", "/"),
        ("hu02-productos", "/productos"),
        ("hu03-recepcion", "/recepciones/crear"),
        ("hu04-venta", "/ventas/crear"),
        ("hu06-alertas", "/alertas"),
        ("hu07-bitacora", "/movimientos"),
        ("hu08-usuarios", "/usuarios"),
        ("hu09-proveedores", "/proveedores"),
        ("hu12-lotes-por-vencer", "/lotes/por-vencer"),
        ("hu13-mermas", "/mermas"),
        ("hu11-ordenes-compra", "/ordenes-compra"),
    ]:
        foto(page, nombre, url)
    ctx.close()

    ctx = nav.new_context(viewport={"width": 1366, "height": 800}, locale="es-CL")
    foto(ctx.new_page(), "hu01-01-login", "/login")
    nav.close()
