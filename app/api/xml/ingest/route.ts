import { NextResponse } from "next/server";

import type { MarketplaceCode } from "../../../../services/marketplaces/base-marketplace.service";
import { enqueueXmlIngestionJob } from "../../../../services/queue/xml-ingestion.queue";

const SUPPORTED_MARKETPLACES: MarketplaceCode[] = ["TRENDYOL", "HEPSIBURADA"];

interface IngestRequestBody {
  userId?: string;
  xmlUrl?: string;
  marketplace?: MarketplaceCode;
  mappingId?: string;
  maxProducts?: number;
}

export async function POST(request: Request) {
  let body: IngestRequestBody;
  try {
    body = (await request.json()) as IngestRequestBody;
  } catch {
    return NextResponse.json(
      {
        ok: false,
        error: "Gecersiz JSON body.",
      },
      { status: 400 },
    );
  }

  if (!body.userId || !body.xmlUrl || !body.marketplace) {
    return NextResponse.json(
      {
        ok: false,
        error: "userId, xmlUrl ve marketplace alanlari zorunludur.",
      },
      { status: 400 },
    );
  }

  if (!SUPPORTED_MARKETPLACES.includes(body.marketplace)) {
    return NextResponse.json(
      {
        ok: false,
        error: "Desteklenmeyen marketplace.",
      },
      { status: 400 },
    );
  }

  try {
    const job = await enqueueXmlIngestionJob({
      userId: body.userId,
      xmlUrl: body.xmlUrl,
      marketplace: body.marketplace,
      mappingId: body.mappingId,
      maxProducts: body.maxProducts,
    });

    return NextResponse.json({
      ok: true,
      jobId: job.id,
      queueName: job.queueName,
    });
  } catch (error) {
    return NextResponse.json(
      {
        ok: false,
        error: error instanceof Error ? error.message : "Kuyruk islemi sirasinda hata olustu.",
      },
      { status: 500 },
    );
  }
}
