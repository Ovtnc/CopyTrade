import { Queue } from "bullmq";

import { redisConnection } from "../../lib/redis";
import type { MarketplaceCode } from "../marketplaces/base-marketplace.service";

export const PRODUCT_SYNC_QUEUE_NAME = "product-sync";

export interface ProductSyncJobData {
  userId: string;
  mappingId?: string;
  sourceUrl: string;
  marketplace: MarketplaceCode;
  product: {
    sku: string;
    title: string;
    description?: string;
    price: number;
    stock: number;
    currency: string;
    raw: Record<string, unknown>;
  };
}

export const productSyncQueue = new Queue<ProductSyncJobData>(PRODUCT_SYNC_QUEUE_NAME, {
  connection: redisConnection,
  defaultJobOptions: {
    attempts: 4,
    backoff: {
      type: "exponential",
      delay: 1500,
    },
    removeOnComplete: 1_000,
    removeOnFail: 2_000,
  },
});

export async function enqueueProductSyncJobs(jobs: ProductSyncJobData[]) {
  if (jobs.length === 0) {
    return [];
  }

  return productSyncQueue.addBulk(
    jobs.map((job) => ({
      name: `sync-${job.marketplace.toLowerCase()}`,
      data: job,
    })),
  );
}
