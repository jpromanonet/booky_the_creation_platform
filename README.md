# Booky

Compañero de escritura para llevar un libro de la ficha al PDF final: autores, estructura (introducción, partes, capítulos, epílogo), borradores, hitos y un dashboard de avance.

PHP + MySQL, sin framework. Interfaz en español, tema claro/oscuro, tipografía DM Sans.

---

## Qué hace

Un **admin** carga autores y libros. Cada libro tiene:

1. **Ficha** — título, subtítulo, descripción y estructura (nombres de partes y lista de secciones).
2. **Hitos** — outline y sinopsis (archivos).
3. **Manuscrito** — cada intro / capítulo / epílogo tiene **dos archivos**:
   - **Borrador** (ODS, ODT o DOCX): Booky cuenta páginas y alimenta el avance. **No** entra al PDF final.
   - **PDF de cierre**: es el que se empalma al exportar. También se guarda su cantidad de páginas (para el índice).
4. **PDF del manuscrito** — portada, índice, hojas “Parte N” y los PDF de cierre, con encabezado y pie en **todas** las páginas.

Un **lector** solo ve los libros que el admin le asigna (lectura y descarga).

### Avance

El porcentaje del libro mezcla outline, sinopsis y cada sección del manuscrito. Las páginas que ves en el dashboard salen del **borrador**, no del PDF de cierre.

### PDF final

Orden del archivo generado:

1. Portada (título, autor, subtítulo)
2. Índice, con números de página del libro completo
3. Una hoja **Parte N** antes de los capítulos de esa parte (si hay al menos un PDF de cierre)
4. Los PDF de cierre, en el orden de la estructura

En **cada** página (también las que subió el autor):

| | Izquierda | Derecha |
|---|---|---|
| Encabezado | Título del libro | Índice / parte / capítulo |
| Pie | Autor | Número corrido (la portada es 1) |

El contenido del PDF del autor no se reescribe: se normaliza a A4, se le superpone el marco de Booky y se empalma con el resto.

Sin PDF de cierre en una sección, esa sección **no** entra al libro. El resto sí.

---

## Requisitos

- PHP 8.1+ con extensiones **pdo_mysql**, **mbstring**, **iconv** y **zip** (`ZipArchive`, para contar páginas de DOCX/ODT/ODS)
- MySQL 8 / MariaDB
- Apache (o cualquier servidor que sirva PHP); las rutas van por `index.php?r=/ruta` (no hace falta rewrite)
- En el servidor, para **Generar PDF**:
  - **qpdf** — obligatorio (empalmar y sellar)
  - **ghostscript** (`gs`) — muy recomendado (normalizar PDFs de Word/LibreOffice a A4)
  - **poppler-utils** (`pdfinfo`) — opcional (detectar tamaño de página)

---

## Instalación local

1. Copiá `.env.example` a `.env` y completá la base:

```env
APP_NAME=Booky
APP_ENV=local
APP_DEBUG=true
APP_URL=

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=booky
DB_USER=root
DB_PASS=
```

2. Creá la base e importá el esquema. Opción A — abrir una vez en el navegador:

   `http://localhost/.../booky/install.php`

   Opción B — a mano:

```bash
mysql -u root -p -e "CREATE DATABASE booky CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p booky < databases/booky.sql
```

3. Entrá con **`admin@booky.local` / `changeme`** y cambiá la contraseña.
4. Borrá o protegé `install.php`.
5. `storage/uploads` y `storage/tmp` tienen que ser escribibles por PHP.

Rutas de ejemplo: `index.php?r=/` (dashboard), `index.php?r=/libros`, `index.php?r=/pdf`.

---

## Ubuntu Server

Paquetes típicos (Apache + PHP + MySQL + herramientas PDF):

```bash
sudo apt update
sudo apt install -y apache2 mysql-server \
  php php-mysql php-mbstring php-xml php-zip php-intl \
  qpdf ghostscript poppler-utils
```

Comprobá que Apache los ve (como `www-data`, no solo en tu sesión SSH):

```bash
which qpdf gs pdfinfo
sudo -u www-data /usr/bin/qpdf --version
sudo -u www-data /usr/bin/gs --version
```

Si Apache no carga zip:

```bash
php -m | grep -i zip
sudo systemctl restart apache2
```

Permisos de almacenamiento (ajustá la ruta si hace falta):

```bash
sudo chown -R www-data:www-data /var/www/html/booky/storage
sudo chmod -R ug+rwx /var/www/html/booky/storage
```

`.env` en el servidor: `APP_ENV=production`, `APP_DEBUG=false`, y **nunca** lo sobrescribas al desplegar.

Después de copiar el código, abrí `install.php` **una vez** si la base está vacía, o dejá que `Schema::ensure()` migre tablas ya existentes. Luego sacá `install.php` del document root.

---

## Generar PDF: qpdf, Ghostscript y qué hace cada uno

Booky **no** reescribe el manuscrito. Usa programas del sistema en cadena:

```
Portada / índice / Parte N  →  las genera Booky (SimplePdf) con encabezado y numeración
PDF de cada capítulo        →  Ghostscript (A4) → sello Booky → qpdf
Todo junto                  →  qpdf --pages (empalme final)
```

| Herramienta | Para qué la usa Booky |
|---|---|
| **qpdf** | Contar páginas, empalmar el libro entero, superponer encabezado/pie en cada PDF del autor. **Sin qpdf no hay export.** |
| **ghostscript** (`gs`) | Reescribir PDFs exportados desde Word/LibreOffice a **A4 simple**. Muchos PDF “raros” no se pueden sellar con qpdf solo; Ghostscript los deja en un formato que sí. |
| **pdfinfo** (poppler) | Detectar tamaño de página (A4 vs Letter). Opcional: si no está, Booky intenta igual. |

### Por qué hace falta Ghostscript si ya tenés qpdf

- **qpdf** une archivos y dibuja el marco encima (o debajo) del manuscrito.
- **Ghostscript** no reemplaza a qpdf: prepara los PDF del autor **antes** del sello.
- Con solo qpdf a veces el libro se empalma bien pero los capítulos quedan **sin encabezado ni número de página** (PDF de Word con capas, fuentes embebidas raras, tamaño distinto de A4).

Instalación en Ubuntu:

```bash
sudo apt install -y qpdf ghostscript poppler-utils
sudo systemctl restart apache2
```

### PHP tiene que poder ejecutar comandos

Booky llama a `qpdf` y `gs` **como el usuario de Apache** (`www-data`), no como tu usuario SSH.

```bash
php -i | grep disable_functions
```

Si aparece `exec` o `proc_open`, sacalos del `php.ini` de Apache/FPM y reiniciá:

```bash
sudo systemctl restart apache2
# o: sudo systemctl restart php8.3-fpm
```

### Errores frecuentes

| Mensaje | Causa habitual |
|---|---|
| “No se pudo empalmar…” | qpdf no instalado, o PHP no puede usar `exec` |
| “No se pudo sellar… (Capítulo X)” | PDF del autor difícil de sellar → instalá **ghostscript** y volvé a generar |
| Empalma OK pero sin marco en capítulos | Código viejo en el servidor, o falta Ghostscript |
| Pie siempre dice “1” | Versión anterior del generador (ya corregido: numeración corrida 1…N) |

Plan B (solo si qpdf falla al empalmar, no para el sello):

```bash
sudo apt install -y pdftk-java
```

---

## Deploy

Subí el código al document root del servidor (por ejemplo `/var/www/html/booky`). **No** incluyas `.git` y **no** sobrescribas el `.env` del servidor.

### Opción A — copia desde otra máquina (Windows)

Si tenés el repo en la PC y el servidor montado por red (SMB), podés usar `robocopy`. Reemplazá las rutas por las tuyas:

```powershell
robocopy "C:\ruta\al\repo\booky" "Z:\booky" /E /XD .git /XF .env
```

Solo archivos concretos (ej. tras un cambio en servicios PDF):

```powershell
robocopy "C:\ruta\al\repo\booky\app\Services" "Z:\booky\app\Services" ManuscriptPdfService.php PdfMergeService.php DocumentService.php SimplePdf.php PageCounter.php
```

### Opción B — copia desde otra máquina (Linux / macOS)

```bash
rsync -av --exclude '.git' --exclude '.env' ./booky/ usuario@servidor:/var/www/html/booky/
```

### Opción C — git en el servidor

```bash
cd /var/www/html/booky
git pull
# .env no va en el repo; no lo pises
```

Después de actualizar PHP, reiniciá el servidor web:

```bash
sudo systemctl restart apache2
# o, con PHP-FPM: sudo systemctl restart php8.3-fpm
```

### Permisos y archivos en el servidor

Los uploads viven en `storage/uploads/` (Apache los deniega por `.htaccess`). Los PDF temporales de exportación van a `storage/tmp/` y se borran al terminar.

No commitees `.env`. El esquema canónico está en `databases/booky.sql`; las bases viejas reciben columnas nuevas al arrancar (`book_parts`, `chapters.kind`, documentos `kind=pdf`).

---

## Cómo usarlo

| Menú | Para qué |
|---|---|
| **Dashboard** | Avance por libro y páginas actuales |
| **Milestones** | Hitos ponderados; se puede filtrar por autor |
| **Libros** | Ficha, estructura, dropzones de borrador y PDF |
| **PDF** | Listado y “Generar PDF” |
| **Autores** | Fichas de autor |
| **Usuarios** | Admin / lector y asignación de libros |
| **Configuración** | Perfil y contraseña |

En la ficha del libro, las **partes** son una lista corta de nombres. Los **capítulos** son una lista plana: cada uno elige “Sin parte” o “Parte I”, etc. Introducción y epílogo son secciones de tipo propio, no capítulos sueltos.

**Consejo:** exportá cada capítulo a PDF en **A4** desde Word o LibreOffice. Así el sello encaja mejor con el marco de Booky.

---

## Roles

- **Admin** — autores, libros, documentos, usuarios, asignación, exportar PDF.
- **Lector** — solo libros asignados; puede leer y descargar, no administrar.

---

## Estructura del código

```
app/Controllers/     rutas HTTP
app/Services/        libros, documentos, conteo de páginas, PDF
  ManuscriptPdfService.php   orquesta portada, índice, sello y empalme
  PdfMergeService.php        qpdf, ghostscript, overlay
  SimplePdf.php              portada, índice, hojas Parte N, sello
  PageCounter.php            páginas en DOCX/ODT/ODS/PDF
  DocumentService.php        subida de borradores y PDF de cierre
app/Views/           plantillas PHP
assets/              CSS y JS
config/              app, database, .env
databases/booky.sql  esquema inicial
storage/uploads/     archivos de autores
storage/tmp/         PDF temporales al exportar (no servir por web)
index.php            router
install.php          alta one-shot de la base
```

PHP 8.1+, un `index.php` y MySQL. Sin Composer.
