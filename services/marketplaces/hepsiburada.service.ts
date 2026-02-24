import {
  MarketplaceProductPayload,
  MarketplaceService,
  MarketplaceSyncResult,
} from "./base-marketplace.service";

export class HepsiburadaService extends MarketplaceService {
  readonly code = "HEPSIBURADA" as const;

  async syncProduct(product: MarketplaceProductPayload): Promise<MarketplaceSyncResult> {
    /*
     * TODO:
     * 1) Hepsiburada merchant listing endpoint formatina map et.
     * 2) Istek basina item limitini dikkate alarak batch'le.
     * 3) API yanitina gore urun bazli hata kodlarini normalize et.
     */
    return {
      success: true,
      externalId: `hepsiburada-${product.sku}`,
      message: "Mock Hepsiburada sync basarili.",
    };
  }
}
