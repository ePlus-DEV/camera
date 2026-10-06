#!/usr/bin/env node

import { readFile, writeFile } from 'node:fs/promises';

const SOURCE_URL = process.env.HCM_CAMERA_SOURCE_URL
  || 'https://giaothong.hochiminhcity.gov.vn/ajaxpro/VDMS.Web.Library.AJAX.FolderAjax,VDMS.Web.Library.ashx';
const DATA_FILE = process.env.CAMERA_DATA_FILE || 'data-camera.json';
const VIETMAP_API_KEY = (process.env.VIETMAP_API_KEY || '').trim();
const HCM_TRAFFIC_COOKIE = (process.env.HCM_TRAFFIC_COOKIE || '').trim();
const MIN_SOURCE_CAMERAS = Number(process.env.MIN_SOURCE_CAMERAS || '700');
const VIETMAP_REQUEST_DELAY_MS = Number(process.env.VIETMAP_REQUEST_DELAY_MS || '150');
const VIETMAP_MAX_REQUESTS = Number(process.env.VIETMAP_MAX_REQUESTS || '1000');
const forceGeocode = process.argv.includes('--force-geocode');
const skipGeocode = process.argv.includes('--skip-geocode');

const SEARCH_PAYLOAD = {
  path: '/root/vdms/tangthu/data/layerdata/camera',
  isInTree: false,
  searchKey: '',
  layer: ['CAMERA'],
  detail: true,
  page: 0,
  limit: -1,
  filterQuery: ['Publish:true'],
  sortby: null,
  returnFields: [
    'CamId',
    'Code',
    'Location',
    'SnapshotUrl',
    'CamType',
    'Disctrict',
    'Publish',
    'ManagementUnit',
    'CamStatus',
    'PTZ',
    'Angle',
    'DisplayName',
  ],
};

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

async function fetchWithRetry(url, options = {}, attempts = 3) {
  let lastError;
  for (let attempt = 1; attempt <= attempts; attempt += 1) {
    try {
      const response = await fetch(url, options);
      if (response.ok) return response;

      const body = (await response.text()).slice(0, 500);
      const error = new Error(`HTTP ${response.status}: ${body}`);
      if (response.status !== 429 && response.status < 500) throw error;
      lastError = error;
    } catch (error) {
      lastError = error;
    }

    if (attempt < attempts) await sleep(500 * (2 ** (attempt - 1)));
  }
  throw lastError;
}

async function fetchSource() {
  const headers = {
    Accept: '*/*',
    'Content-Type': 'application/json; charset=UTF-8',
    'X-AjaxPro-Method': 'SearchQuery',
    Origin: 'https://giaothong.hochiminhcity.gov.vn',
    Referer: 'https://giaothong.hochiminhcity.gov.vn/',
    'User-Agent': 'camera-hcm-sync/1.0 (+https://camera-hcm.eplus.dev/)',
  };
  if (HCM_TRAFFIC_COOKIE) headers.Cookie = HCM_TRAFFIC_COOKIE;

  const response = await fetchWithRetry(SOURCE_URL, {
    method: 'POST',
    headers,
    body: JSON.stringify(SEARCH_PAYLOAD),
    redirect: 'follow',
  });

  return response.text();
}

function parseAjaxDataTable(input) {
  let index = input.indexOf('new Ajax.Web.DataTable(');
  if (index < 0) throw new Error('Camera source did not contain Ajax.Web.DataTable data.');

  const skipWhitespace = () => {
    while (index < input.length && /\s/.test(input[index])) index += 1;
  };

  const parseString = () => {
    const start = index;
    index += 1;
    let escaped = false;
    while (index < input.length) {
      const char = input[index++];
      if (escaped) {
        escaped = false;
      } else if (char === '\\') {
        escaped = true;
      } else if (char === '"') {
        break;
      }
    }
    return JSON.parse(input.slice(start, index));
  };

  const parseValue = () => {
    skipWhitespace();

    if (input.startsWith('new Ajax.Web.DataTable(', index)) {
      index += 'new Ajax.Web.DataTable('.length;
      const cols = parseValue();
      skipWhitespace();
      if (input[index++] !== ',') throw new Error('Invalid DataTable column separator.');
      const rows = parseValue();
      skipWhitespace();
      if (input[index++] !== ')') throw new Error('Invalid DataTable closing token.');
      return { cols, rows };
    }

    if (input[index] === '[') {
      index += 1;
      const values = [];
      skipWhitespace();
      if (input[index] === ']') {
        index += 1;
        return values;
      }
      while (true) {
        values.push(parseValue());
        skipWhitespace();
        if (input[index] === ']') {
          index += 1;
          return values;
        }
        if (input[index++] !== ',') throw new Error('Invalid array separator.');
      }
    }

    if (input[index] === '"') return parseString();

    for (const [literal, value] of [['null', null], ['true', true], ['false', false]]) {
      if (input.startsWith(literal, index)) {
        index += literal.length;
        return value;
      }
    }

    const number = input.slice(index).match(/^-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?/);
    if (!number) throw new Error(`Unexpected source token near offset ${index}.`);
    index += number[0].length;
    return Number(number[0]);
  };

  return parseValue();
}

function tableToObjects(table) {
  const columns = table.cols.map(column => Array.isArray(column) ? column[0] : column);
  return table.rows.map(row => Object.fromEntries(columns.map((column, i) => [column, row[i]])));
}

function extractCoordinates(location) {
  if (!location || !Array.isArray(location.cols) || !Array.isArray(location.rows) || !location.rows[0]) {
    return null;
  }

  const columns = location.cols.map(column => Array.isArray(column) ? column[0] : column);
  const shapeIndex = columns.indexOf('Shape');
  if (shapeIndex < 0) return null;

  const shape = String(location.rows[0][shapeIndex] ?? '');
  const match = shape.match(/^POINT\(\s*(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s*\)$/i);
  if (!match) return null;

  const longitude = Number(match[1]);
  const latitude = Number(match[2]);
  if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return null;
  if (latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) return null;

  return { Latitude: latitude, Longitude: longitude };
}

function normalizeSourceCamera(source) {
  const coordinates = extractCoordinates(source.Location);
  return {
    CamId: String(source.CamId || ''),
    CamName: String(source.DisplayName || source.Code || source.Title || source.CamId || '').trim(),
    Disctrict: String(source.Disctrict || 'N/A').trim(),
    SnapshotUrl: source.SnapshotUrl ? String(source.SnapshotUrl) : (source.CamType ? String(source.CamType) : ''),
    ManagementUnit: source.ManagementUnit ? String(source.ManagementUnit) : false,
    ...(coordinates || {}),
  };
}

function isValidCamId(value) {
  return /^[a-f0-9]{24}$/i.test(String(value || ''));
}

function coordinateChanged(before, after) {
  return before.Latitude !== after.Latitude || before.Longitude !== after.Longitude;
}

function mergeSource(current, sourceRows) {
  const currentIds = new Set();
  for (const camera of current) {
    if (!isValidCamId(camera.CamId)) throw new Error(`Invalid CamId in current data: ${camera.CamId}`);
    if (currentIds.has(camera.CamId)) throw new Error(`Duplicate CamId in current data: ${camera.CamId}`);
    currentIds.add(camera.CamId);
  }

  const sourceIds = new Set();
  const sourceById = new Map();
  for (const source of sourceRows) {
    const normalized = normalizeSourceCamera(source);
    if (!isValidCamId(normalized.CamId)) continue;
    if (sourceIds.has(normalized.CamId)) throw new Error(`Duplicate CamId in source: ${normalized.CamId}`);
    sourceIds.add(normalized.CamId);
    sourceById.set(normalized.CamId, normalized);
  }

  if (sourceById.size < MIN_SOURCE_CAMERAS) {
    throw new Error(`Source safety check failed: expected at least ${MIN_SOURCE_CAMERAS} cameras, got ${sourceById.size}.`);
  }

  let added = 0;
  let coordinatesUpdated = 0;
  const coordinatesChanged = new Set();

  const merged = current.map(camera => {
    const source = sourceById.get(camera.CamId);
    if (!source) return camera;

    const next = { ...camera };
    if (Number.isFinite(source.Latitude) && Number.isFinite(source.Longitude)) {
      const candidate = { ...next, Latitude: source.Latitude, Longitude: source.Longitude };
      if (coordinateChanged(next, candidate)) {
        next.Latitude = source.Latitude;
        next.Longitude = source.Longitude;
        coordinatesUpdated += 1;
        coordinatesChanged.add(camera.CamId);
      }
    }

    if ((!next.Disctrict || next.Disctrict === 'N/A') && source.Disctrict && source.Disctrict !== 'N/A') {
      next.Disctrict = source.Disctrict;
    }
    return next;
  });

  for (const [id, source] of sourceById) {
    if (currentIds.has(id)) continue;
    merged.push(source);
    currentIds.add(id);
    added += 1;
    if (Number.isFinite(source.Latitude) && Number.isFinite(source.Longitude)) coordinatesChanged.add(id);
  }

  return {
    cameras: merged,
    sourceIds,
    coordinatesChanged,
    added,
    coordinatesUpdated,
  };
}

function getBoundary(boundaries, type) {
  const item = Array.isArray(boundaries) ? boundaries.find(boundary => Number(boundary?.type) === type) : null;
  return item?.full_name || item?.name || '';
}

async function reverseGeocode(camera) {
  const url = new URL('https://maps.vietmap.vn/api/reverse/v4');
  url.searchParams.set('apikey', VIETMAP_API_KEY);
  url.searchParams.set('lat', String(camera.Latitude));
  url.searchParams.set('lng', String(camera.Longitude));
  url.searchParams.set('display_type', '6');

  const response = await fetchWithRetry(url, {
    headers: {
      Accept: 'application/json',
      'User-Agent': 'camera-hcm-sync/1.0 (+https://camera-hcm.eplus.dev/)',
    },
  });
  const payload = await response.json();
  const result = Array.isArray(payload) ? payload[0] : null;
  if (!result) return null;

  const boundaries = result.boundaries || [];
  const Ward = getBoundary(boundaries, 2);
  const District = getBoundary(boundaries, 1);
  const Province = getBoundary(boundaries, 0);
  const Address = String(result.display || [result.name, result.address].filter(Boolean).join(', ')).trim();

  return { Address, Ward, District, Province };
}

function needsGeocode(camera, coordinatesChanged) {
  if (!Number.isFinite(camera.Latitude) || !Number.isFinite(camera.Longitude)) return false;
  if (forceGeocode || coordinatesChanged.has(camera.CamId)) return true;
  return !camera.Address || !camera.Ward || !camera.District || !camera.Province;
}

async function enrichAddresses(cameras, coordinatesChanged) {
  if (skipGeocode) return { requested: 0, updated: 0, failed: 0 };
  if (!VIETMAP_API_KEY) {
    console.log('VIETMAP_API_KEY is not configured; address enrichment skipped.');
    return { requested: 0, updated: 0, failed: 0 };
  }

  let requested = 0;
  let updated = 0;
  let failed = 0;

  for (const camera of cameras) {
    if (!needsGeocode(camera, coordinatesChanged)) continue;
    if (requested >= VIETMAP_MAX_REQUESTS) {
      console.warn(`Reached VIETMAP_MAX_REQUESTS=${VIETMAP_MAX_REQUESTS}; remaining cameras will be retried next run.`);
      break;
    }

    requested += 1;
    try {
      const location = await reverseGeocode(camera);
      if (!location || !location.Address) {
        failed += 1;
        console.warn(`No VietMap result for ${camera.CamId} (${camera.CamName}).`);
      } else {
        camera.Address = location.Address;
        if (location.Ward) camera.Ward = location.Ward;
        if (location.District) {
          camera.District = location.District;
          if (!camera.Disctrict || camera.Disctrict === 'N/A') camera.Disctrict = location.District;
        }
        if (location.Province) camera.Province = location.Province;
        updated += 1;
      }
    } catch (error) {
      failed += 1;
      console.warn(`VietMap failed for ${camera.CamId}: ${error.message}`);
    }

    if (VIETMAP_REQUEST_DELAY_MS > 0) await sleep(VIETMAP_REQUEST_DELAY_MS);
  }

  return { requested, updated, failed };
}

async function main() {
  const current = JSON.parse(await readFile(DATA_FILE, 'utf8'));
  if (!Array.isArray(current) || current.length === 0) throw new Error(`${DATA_FILE} must contain a non-empty array.`);

  console.log(`Current dataset: ${current.length} cameras.`);
  const sourceText = await fetchSource();
  const sourceTable = parseAjaxDataTable(sourceText);
  const sourceRows = tableToObjects(sourceTable);
  console.log(`Published source: ${sourceRows.length} rows.`);

  const merged = mergeSource(current, sourceRows);
  const geocode = await enrichAddresses(merged.cameras, merged.coordinatesChanged);

  const stale = merged.cameras.filter(camera => !merged.sourceIds.has(camera.CamId)).length;
  await writeFile(DATA_FILE, `${JSON.stringify(merged.cameras, null, 2)}\n`, 'utf8');

  console.log(JSON.stringify({
    total: merged.cameras.length,
    source: merged.sourceIds.size,
    added: merged.added,
    coordinatesUpdated: merged.coordinatesUpdated,
    staleKept: stale,
    geocode,
  }, null, 2));
}

main().catch(error => {
  console.error(error.stack || error.message || error);
  process.exitCode = 1;
});
