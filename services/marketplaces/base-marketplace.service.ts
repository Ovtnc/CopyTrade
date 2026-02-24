export type MarketplaceCode = "TRENDYOL" | "HEPSIBURADA";

export interface MarketplaceProductPayload {
  sku: string;
  title: string;
  description?: string;
  price: number;
  stock: number;
  currency: string;
  raw?: Record<string, unknown>;
}

export interface MarketplaceSyncResult {
  success: boolean;
  externalId?: string;
  message?: string;
  rawResponse?: unknown;
}

export abstract class MarketplaceService {
  abstract readonly code: MarketplaceCode;

  abstract syncProduct(product: MarketplaceProductPayload): Promise<MarketplaceSyncResult>;
}
