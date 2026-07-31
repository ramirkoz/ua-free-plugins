from pathlib import Path
from zipfile import ZipFile, ZipInfo, ZIP_DEFLATED
import hashlib, json, shutil

repo = Path('.')
releases = repo / 'releases'
source = repo / 'ua-free-analytics-dashboard'
candidate = Path('/tmp/ua-free-suite-control-center-0.3.12.zip')
fixed_time = (2026, 7, 31, 14, 45, 0)
with ZipFile(candidate, 'w', ZIP_DEFLATED, compresslevel=9) as archive:
    for path in sorted(source.rglob('*')):
        if not path.is_file():
            continue
        arcname = Path('ua-free-analytics-dashboard') / path.relative_to(source)
        info = ZipInfo(str(arcname).replace('\\', '/'), fixed_time)
        info.compress_type = ZIP_DEFLATED
        info.external_attr = 0o644 << 16
        archive.writestr(info, path.read_bytes())

plugin_sha = hashlib.sha256(candidate.read_bytes()).hexdigest()
expected = 'e40a4b8513608b2ea280d51fb7dee16357c6ef4bf14dec83b1e9cec5bc8fa3ca'
if plugin_sha != expected:
    raise SystemExit(f'Unexpected plugin SHA: {plugin_sha}')
with ZipFile(candidate) as archive:
    if archive.testzip() is not None:
        raise SystemExit('Control Center ZIP CRC failure')

release_zip = releases / 'ua-free-suite-control-center-0.3.12.zip'
shutil.copy2(candidate, release_zip)
(releases / 'ua-free-suite-control-center-0.3.12.sha256').write_text(
    f'{plugin_sha}  releases/ua-free-suite-control-center-0.3.12.zip\n', encoding='utf-8'
)
for old in [
    releases / 'ua-free-suite-control-center-0.3.11.zip',
    releases / 'ua-free-suite-control-center-0.3.11.sha256',
]:
    old.unlink(missing_ok=True)

old_library = releases / 'UA_FREE_HUB_RELEASE_LIBRARY_FINAL_2026-07-31_v2.8.zip'
with ZipFile(old_library) as archive:
    if archive.testzip() is not None:
        raise SystemExit('Library 2.8 CRC failure')
    manifest = json.loads(archive.read('manifest.json'))
    packages = {
        name: archive.read(name)
        for name in archive.namelist()
        if name.startswith('packages/')
    }

manifest['version'] = '2.9'
manifest['created'] = '2026-07-31'
manifest['status'] = 'FINAL'
packages.pop('packages/ua-free-suite-control-center-v0.3.11.zip', None)
new_package_name = 'packages/ua-free-suite-control-center-v0.3.12.zip'
packages[new_package_name] = candidate.read_bytes()
for package in manifest['packages']:
    if package['slug'] == 'ua-free-suite-control-center':
        package.update({
            'version': '0.3.12',
            'file': new_package_name,
            'sha256': plugin_sha,
            'size': candidate.stat().st_size,
        })
        break
else:
    raise SystemExit('Control Center missing from manifest')

for package in manifest['packages']:
    data = packages[package['file']]
    if hashlib.sha256(data).hexdigest() != package['sha256']:
        raise SystemExit(f"Package SHA mismatch: {package['file']}")
    if len(data) != package['size']:
        raise SystemExit(f"Package size mismatch: {package['file']}")

manifest_text = json.dumps(manifest, ensure_ascii=False, indent=2) + '\n'
library_name = 'UA_FREE_HUB_RELEASE_LIBRARY_FINAL_2026-07-31_v2.9.zip'
library = releases / library_name
library_time = (2026, 7, 31, 14, 50, 0)
with ZipFile(library, 'w', ZIP_DEFLATED, compresslevel=9) as archive:
    info = ZipInfo('manifest.json', library_time)
    info.compress_type = ZIP_DEFLATED
    info.external_attr = 0o644 << 16
    archive.writestr(info, manifest_text.encode('utf-8'))
    for package in sorted(manifest['packages'], key=lambda item: item['file']):
        info = ZipInfo(package['file'], library_time)
        info.compress_type = ZIP_DEFLATED
        info.external_attr = 0o644 << 16
        archive.writestr(info, packages[package['file']])

with ZipFile(library) as archive:
    if archive.testzip() is not None:
        raise SystemExit('Library 2.9 CRC failure')
    embedded = json.loads(archive.read('manifest.json'))
    if embedded != manifest or len(embedded['packages']) != 12:
        raise SystemExit('Library 2.9 manifest mismatch')
    for package in embedded['packages']:
        data = archive.read(package['file'])
        if hashlib.sha256(data).hexdigest() != package['sha256']:
            raise SystemExit(f"Embedded SHA mismatch: {package['file']}")

library_sha = hashlib.sha256(library.read_bytes()).hexdigest()
(releases / f'{library_name}.sha256').write_text(
    f'{library_sha}  releases/{library_name}\n', encoding='utf-8'
)
(releases / 'UA_FREE_HUB_RELEASE_LIBRARY_FINAL_2026-07-31_v2.9.manifest.json').write_text(
    manifest_text, encoding='utf-8'
)
(releases / 'UA_FREE_SYNC_REPORT_2026-07-31_v2.9.md').write_text(
    '# UA FREE Synchronization Report\n\n'
    '- Date: 2026-07-31\n'
    '- Library: 2.9\n'
    '- Status: Suite Control Center 0.3.12 Plugin Check PASS; package, manifest, ZIP, CRC, SHA-256 and PHP checks PASS.\n'
    '- Scope: Suite Control Center updated; other 11 public packages and SHA-256 values preserved.\n\n'
    f'Plugin SHA-256: `{plugin_sha}`\n\n'
    f'Library SHA-256: `{library_sha}`\n',
    encoding='utf-8',
)
print(f'PLUGIN_SHA={plugin_sha}')
print(f'LIBRARY_SHA={library_sha}')
