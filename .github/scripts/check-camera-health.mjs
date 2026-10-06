import fs from "node:fs/promises"

const PROXY_BASE = process.env.CAMERA_PROXY_URL || "https://camera-proxy.eplus.dev/camera"
const ORIGIN = process.env.CAMERA_PROXY_ORIGIN || "https://eplus.dev"
const CONCURRENCY = Number(process.env.CAMERA_HEALTH_CONCURRENCY || 12)
const TIMEOUT_MS = Number(process.env.CAMERA_HEALTH_TIMEOUT_MS || 6000)
const OFFLINE_AFTER = Number(process.env.CAMERA_HEALTH_OFFLINE_AFTER || 2)
const PREVIOUS_URL =
  process.env.CAMERA_HEALTH_PREVIOUS_URL ||
  "https://raw.githubusercontent.com/ePlus-DEV/camera/status/camera-status.json"

async function loadPrevious() {
  try {
    const response = await fetch(`${PREVIOUS_URL}?t=${Date.now()}`, { cache: "no-store" })
    if (!response.ok) return { cameras: {} }
    const json = await response.json()
    return json && typeof json === "object" ? json : { cameras: {} }
  } catch {
    return { cameras: {} }
  }
}

async function probe(camera) {
  const startedAt = Date.now()
  const controller = new AbortController()
  const timeout = setTimeout(() => controller.abort(), TIMEOUT_MS)

  try {
    const url = new URL(PROXY_BASE)
    url.searchParams.set("id", camera.CamId)
    url.searchParams.set("health", String(Date.now()))

    const response = await fetch(url, {
      method: "GET",
      headers: {
        Origin: ORIGIN,
        Accept: "image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8",
        "Cache-Control": "no-cache",
      },
      cache: "no-store",
      signal: controller.signal,
    })

    const contentType = response.headers.get("content-type") || ""
    if (!response.ok) {
      return {
        ok: false,
        httpStatus: response.status,
        contentType,
        latencyMs: Date.now() - startedAt,
        error: `HTTP ${response.status}`,
      }
    }

    if (!contentType.toLowerCase().startsWith("image/")) {
      return {
        ok: false,
        httpStatus: response.status,
        contentType,
        latencyMs: Date.now() - startedAt,
        error: "Non-image response",
      }
    }

    const reader = response.body?.getReader()
    if (!reader) {
      return {
        ok: false,
        httpStatus: response.status,
        contentType,
        latencyMs: Date.now() - startedAt,
        error: "Missing response body",
      }
    }

    const { value, done } = await reader.read()
    await reader.cancel().catch(() => {})

    if (done || !value || value.byteLength === 0) {
      return {
        ok: false,
        httpStatus: response.status,
        contentType,
        latencyMs: Date.now() - startedAt,
        error: "Empty image body",
      }
    }

    return {
      ok: true,
      httpStatus: response.status,
      contentType,
      latencyMs: Date.now() - startedAt,
      firstChunkBytes: value.byteLength,
    }
  } catch (error) {
    return {
      ok: false,
      httpStatus: null,
      contentType: "",
      latencyMs: Date.now() - startedAt,
      error: error?.name === "AbortError" ? "Timeout" : String(error?.message || error),
    }
  } finally {
    clearTimeout(timeout)
  }
}

async function mapLimit(items, concurrency, mapper) {
  const results = new Array(items.length)
  let cursor = 0

  async function worker() {
    while (true) {
      const index = cursor++
      if (index >= items.length) return
      results[index] = await mapper(items[index], index)
    }
  }

  await Promise.all(Array.from({ length: Math.max(1, concurrency) }, () => worker()))
  return results
}

async function main() {
  const startedAt = Date.now()
  const raw = JSON.parse(await fs.readFile("data-camera.json", "utf8"))
  const cameras = raw.filter((camera) => /^[a-f0-9]{24}$/i.test(String(camera?.CamId || "")))
  const previous = await loadPrevious()
  const previousCameras = previous?.cameras || {}

  const checks = await mapLimit(cameras, CONCURRENCY, async (camera, index) => {
    const result = await probe(camera)
    const previousCamera = previousCameras[camera.CamId] || {}
    const checkedAt = new Date().toISOString()

    if (result.ok) {
      return [
        camera.CamId,
        {
          name: camera.CamName || "",
          status: "online",
          checkedAt,
          lastSuccessAt: checkedAt,
          consecutiveFailures: 0,
          httpStatus: result.httpStatus,
          contentType: result.contentType,
          latencyMs: result.latencyMs,
          currentCheckOk: true,
        },
      ]
    }

    const consecutiveFailures = Number(previousCamera.consecutiveFailures || 0) + 1
    let status = "unknown"

    if (consecutiveFailures >= OFFLINE_AFTER) {
      status = "offline"
    } else if (previousCamera.status === "online") {
      status = "online"
    }

    return [
      camera.CamId,
      {
        name: camera.CamName || "",
        status,
        checkedAt,
        lastSuccessAt: previousCamera.lastSuccessAt || null,
        consecutiveFailures,
        httpStatus: result.httpStatus,
        contentType: result.contentType,
        latencyMs: result.latencyMs,
        currentCheckOk: false,
        error: result.error,
      },
    ]
  })

  const cameraStatus = Object.fromEntries(checks)
  const values = Object.values(cameraStatus)
  const summary = {
    total: values.length,
    online: values.filter((camera) => camera.status === "online").length,
    offline: values.filter((camera) => camera.status === "offline").length,
    unknown: values.filter((camera) => camera.status === "unknown").length,
  }

  const output = {
    version: 1,
    source: PROXY_BASE,
    generatedAt: new Date().toISOString(),
    durationMs: Date.now() - startedAt,
    checker: {
      concurrency: CONCURRENCY,
      timeoutMs: TIMEOUT_MS,
      offlineAfterConsecutiveFailures: OFFLINE_AFTER,
    },
    ...summary,
    cameras: cameraStatus,
  }

  await fs.mkdir("status", { recursive: true })
  await fs.writeFile("status/camera-status.json", JSON.stringify(output, null, 2) + "\n")

  console.log(JSON.stringify({ ...summary, durationMs: output.durationMs }, null, 2))
}

main().catch((error) => {
  console.error(error)
  process.exit(1)
})
