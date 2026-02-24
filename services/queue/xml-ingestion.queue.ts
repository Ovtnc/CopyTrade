import { JobsOptions, Queue, QueueEvents } from "bullmq";

import { redisConnection } from "../../lib/redis";
import type { MarketplaceCode } from "../marketplaces/base-marketplace.service";

export const XML_INGESTION_QUEUE_NAME = "xml-ingestion";

export interface XmlIngestionJobData {
  userId: string;
  xmlUrl: string;
  marketplace: MarketplaceCode;
  mappingId?: string;
  maxProducts?: number;
}

export const xmlIngestionQueue = new Queue<XmlIngestionJobData>(XML_INGESTION_QUEUE_NAME, {
  connection: redisConnection,
  defaultJobOptions: {
    attempts: 3,
    removeOnComplete: 500,
    removeOnFail: 500,
  },
});

export const xmlIngestionQueueEvents = new QueueEvents(XML_INGESTION_QUEUE_NAME, {
  connection: redisConnection,
});

export async function enqueueXmlIngestionJob(
  data: XmlIngestionJobData,
  options?: JobsOptions,
) {
  return xmlIngestionQueue.add(`ingest-${data.marketplace.toLowerCase()}`, data, options);
}
