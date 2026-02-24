import {
  MarketplaceProductPayload,
  MarketplaceService,
  MarketplaceSyncResult,
} from "./base-marketplace.service";

export class TrendyolService extends MarketplaceService {
  readonly code = "TRENDYOL" as const;

  async syncProduct(product: MarketplaceProductPayload): Promise<MarketplaceSyncResult> {
    /*
     * TODO:
     * 1) Trendyol supplier API endpoint'ine donusturulmus payload gonder.
     * 2) 429/5xx durumlarinda retry/backoff mekanizmasini guclendir.
     * 3) Basarili yanitta donen batch item id bilgisini externalId olarak kaydet.
     */
    return {
      success: true,
      externalId: `trendyol-${product.sku}`,
      message: "Mock Trendyol sync basarili.",
    };
  }
}
