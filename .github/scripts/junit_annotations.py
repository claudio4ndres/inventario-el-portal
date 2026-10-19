"""Publica los fallos de las pruebas como anotaciones de GitHub Actions.

Así cada prueba fallida aparece con su mensaje en la pestaña "Checks" del commit.
Uso: python3 .github/scripts/junit_annotations.py junit.xml test-output.txt
"""
import os
import sys
import xml.etree.ElementTree as ET


def escapar(texto: str) -> str:
    return texto.replace('%', '%25').replace('\r', '%0D').replace('\n', '%0A')


junit, salida = sys.argv[1], sys.argv[2]
fallos = 0
try:
    for caso in ET.parse(junit).getroot().iter('testcase'):
        for tipo in ('failure', 'error'):
            nodo = caso.find(tipo)
            if nodo is None:
                continue
            fallos += 1
            detalle = (nodo.text or nodo.get('message') or '').strip()[:1800]
            titulo = f"{caso.get('class', '')}::{caso.get('name', '')}"
            print(f"::error title={escapar(titulo)}::{escapar(detalle)}")
except Exception as e:  # junit ausente o incompleto: se usa la salida de consola
    print(f"No se pudo leer {junit}: {e}")

if fallos == 0 and os.path.exists(salida):
    with open(salida, encoding='utf-8', errors='replace') as f:
        cola = f.read()[-4000:]
    print(f"::error title=Salida de las pruebas (final)::{escapar(cola)}")
print(f"{fallos} prueba(s) con fallos")
