"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";

export default function POSDashboard() {
  const router = useRouter();

  useEffect(() => {
    router.replace("/modules/pos");
  }, [router]);

  return null;
}
