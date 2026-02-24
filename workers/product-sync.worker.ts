import { Worker } from "bullmq";

import { redisConnection } from "../lib/redis";
import { createMarketplaceService } from "../services/marketplaces/factory";
import {
  PRODUCT_SYNC_QUEUE_NAME,
} from "../services/queue/product-sync.queue";
import type { ProductSyncJobData } from "../services/queue/product-sync.queue";

export const productSyncWorker = new Worker<ProductSyncJobData>(
  PRODUCT_SYNC_QUEUE_NAME,
  async (job) => {
    const marketplaceService = createMarketplaceService(job.data.marketplace);
    const result = await marketplaceService.syncProduct(job.data.product);

    if (!result.success) {
      throw new Error(result.message ?? "Marketplace sync islemi basarisiz.");
    }

    return {
      sku: job.data.product.sku,
      marketplace: job.data.marketplace,
      externalId: result.externalId,
    };
  },
  {
    connection: redisConnection,
    concurrency: 6,
    limiter: {
      max: 40,
      duration: 1000,
    },
  },
);

productSyncWorker.on("completed", (job, result) => {
  console.info(`[product-sync] Job ${job.id} tamamlandi`, result);
});

productSyncWorker.on("failed", (job, error) => {
  console.error(`[product-sync] Job ${job?.id} basarisiz`, error);
});
