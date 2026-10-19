"""Convierte los fallos de un reporte JUnit en anotaciones de GitHub Actions.

Así cada prueba fallida aparece con su mensaje en la pestaña "Checks" del commit.
Uso: python3 .github/scripts/junit_annotations.py junit.xml
"""
import sys
import xml.etree.ElementTree as ET


def escapar(texto: str) -> str:
    return texto.replace('%', '%25').replace('\r', '%0D').replace('\n', '%0A')


import os
import subprocess

if not os.path.exists(sys.argv[1]):
    # Las pruebas no alcanzaron a ejecutarse (error de arranque): se publica el final de la salida
    salida = subprocess.run(['php', 'artisan', 'test', '--no-ansi'], capture_output=True, text=True)
    texto = (salida.stdout + salida.stderr)[-3000:]
    print(f"::error title=Las pruebas no se ejecutaron::{escapar(texto)}")
    sys.exit(0)

raiz = ET.parse(sys.argv[1]).getroot()
fallos = 0
for caso in raiz.iter('testcase'):
    for tipo in ('failure', 'error'):
        nodo = caso.find(tipo)
        if nodo is None:
            continue
        fallos += 1
        detalle = (nodo.text or nodo.get('message') or '').strip()[:1800]
        archivo = caso.get('file', '')
        linea = caso.get('line', '1')
        titulo = f"{caso.get('class', '')}::{caso.get('name', '')}"
        print(f"::error file={archivo},line={linea},title={escapar(titulo)}::{escapar(detalle)}")
print(f"{fallos} prueba(s) con fallos")
