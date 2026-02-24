import { HepsiburadaService } from "./hepsiburada.service";
import type { MarketplaceCode, MarketplaceService } from "./base-marketplace.service";
import { TrendyolService } from "./trendyol.service";

export function createMarketplaceService(code: MarketplaceCode): MarketplaceService {
  switch (code) {
    case "TRENDYOL":
      return new TrendyolService();
    case "HEPSIBURADA":
      return new HepsiburadaService();
    default:
      throw new Error(`Desteklenmeyen marketplace tipi: ${code}`);
  }
}
