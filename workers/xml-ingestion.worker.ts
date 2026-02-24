import { Worker } from "bullmq";

import { redisConnection } from "../lib/redis";
import {
  enqueueProductSyncJobs,
} from "../services/queue/product-sync.queue";
import type { ProductSyncJobData } from "../services/queue/product-sync.queue";
import {
  XML_INGESTION_QUEUE_NAME,
} from "../services/queue/xml-ingestion.queue";
import type { XmlIngestionJobData } from "../services/queue/xml-ingestion.queue";
import { fetchAndNormalizeXml } from "../services/xml/xml-parser.service";

export const xmlIngestionWorker = new Worker<XmlIngestionJobData>(
  XML_INGESTION_QUEUE_NAME,
  async (job) => {
    const products = await fetchAndNormalizeXml(job.data.xmlUrl);
    const limitedProducts = products.slice(
      0,
      job.data.maxProducts && job.data.maxProducts > 0 ? job.data.maxProducts : products.length,
    );

    const queuePayload: ProductSyncJobData[] = limitedProducts.map((product) => ({
      userId: job.data.userId,
      mappingId: job.data.mappingId,
      sourceUrl: job.data.xmlUrl,
      marketplace: job.data.marketplace,
      product,
    }));

    await enqueueProductSyncJobs(queuePayload);

    return {
      totalParsed: products.length,
      queued: queuePayload.length,
      dropped: products.length - queuePayload.length,
    };
  },
  {
    connection: redisConnection,
    concurrency: 2,
  },
);

xmlIngestionWorker.on("completed", (job, result) => {
  console.info(`[xml-ingestion] Job ${job.id} tamamlandi`, result);
});

xmlIngestionWorker.on("failed", (job, error) => {
  console.error(`[xml-ingestion] Job ${job?.id} basarisiz`, error);
});
