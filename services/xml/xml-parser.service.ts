import { randomUUID } from "node:crypto";
import { XMLParser } from "fast-xml-parser";

export interface NormalizedXmlProduct {
  sku: string;
  title: string;
  description?: string;
  price: number;
  stock: number;
  currency: string;
  raw: Record<string, unknown>;
}

const xmlParser = new XMLParser({
  ignoreAttributes: false,
  attributeNamePrefix: "@_",
  trimValues: true,
  parseTagValue: true,
});

function toNumber(value: unknown, fallback = 0): number {
  if (typeof value === "number" && Number.isFinite(value)) {
    return value;
  }
  if (typeof value === "string") {
    const normalized = value.replace(",", ".").replace(/[^0-9.-]/g, "");
    const parsed = Number.parseFloat(normalized);
    return Number.isFinite(parsed) ? parsed : fallback;
  }
  return fallback;
}

function toText(value: unknown): string | undefined {
  if (typeof value === "string" && value.trim().length > 0) {
    return value.trim();
  }
  if (typeof value === "number") {
    return String(value);
  }
  if (value && typeof value === "object" && "#text" in value) {
    return toText((value as Record<string, unknown>)["#text"]);
  }
  return undefined;
}

function getField(raw: Record<string, unknown>, keys: string[]): unknown {
  const entries = Object.entries(raw);
  for (const key of keys) {
    const matched = entries.find(([field]) => field.toLowerCase() === key.toLowerCase());
    if (matched) {
      return matched[1];
    }
  }
  return undefined;
}

function looksLikeProductNode(node: Record<string, unknown>): boolean {
  const keys = Object.keys(node).map((key) => key.toLowerCase());
  return keys.some((key) => ["sku", "stockcode", "barcode", "title", "name", "price"].includes(key));
}

function findProductNodes(input: unknown): Record<string, unknown>[] {
  if (Array.isArray(input)) {
    return input
      .filter((item): item is Record<string, unknown> => !!item && typeof item === "object")
      .flatMap((item) => (looksLikeProductNode(item) ? [item] : findProductNodes(item)));
  }

  if (!input || typeof input !== "object") {
    return [];
  }

  const node = input as Record<string, unknown>;
  if (looksLikeProductNode(node)) {
    return [node];
  }

  return Object.values(node).flatMap((child) => findProductNodes(child));
}

export function normalizeProductsFromXml(xmlRaw: string): NormalizedXmlProduct[] {
  const parsed = xmlParser.parse(xmlRaw);
  const nodes = findProductNodes(parsed);

  return nodes
    .map((raw): NormalizedXmlProduct | null => {
      const sku =
        toText(getField(raw, ["sku", "stockCode", "stockcode", "barcode", "id", "productCode"])) ??
        randomUUID();
      const title = toText(getField(raw, ["title", "name", "productName", "urunAdi", "product"])) ?? "";

      if (!title) {
        return null;
      }

      const description = toText(
        getField(raw, ["description", "detail", "details", "aciklama", "productDescription"]),
      );
      const price = toNumber(getField(raw, ["price", "salePrice", "amount", "fiyat"]));
      const stock = Math.max(0, Math.floor(toNumber(getField(raw, ["stock", "quantity", "adet", "inventory"]))));
      const currency = toText(getField(raw, ["currency", "curr", "paraBirimi"])) ?? "TRY";

      return {
        sku,
        title,
        description,
        price,
        stock,
        currency,
        raw,
      };
    })
    .filter((item): item is NormalizedXmlProduct => item !== null);
}

export async function fetchAndNormalizeXml(xmlUrl: string): Promise<NormalizedXmlProduct[]> {
  const response = await fetch(xmlUrl);
  if (!response.ok) {
    throw new Error(`XML kaynagi okunamadi: ${response.status} ${response.statusText}`);
  }

  const xmlRaw = await response.text();
  const normalizedProducts = normalizeProductsFromXml(xmlRaw);
  if (normalizedProducts.length === 0) {
    throw new Error("XML icinde islenebilir urun kaydi bulunamadi.");
  }

  return normalizedProducts;
}
