import { useEffect, useRef, useState } from 'react';

type Snapshot = {
  serverTime: string;
  version: string;
  wallet: { id:number; currency:string; availableMinor:string; heldMinor:string; status:string; updatedAt:string|null } | null;
  transactions: Array<{id:number;uuid:string;reference:string;status:string;amountMinor:string;feeMinor:string;totalMinor:string;currency:string;providerStatus:string|null;updatedAt:string|null;completedAt:string|null;terminal:boolean}>;
  notifications: {unreadCount:number;latestId:string|null};
};

const DEFAULT_ACTIVE_MS = 4000;
const DEFAULT_IDLE_MS = 15000;

export function useRealtimeUpdates(onUpdate:(snapshot:Snapshot)=>void, options?:{enabled?:boolean; activeMs?:number; idleMs?:number; stopWhenTerminal?:boolean; watchReferences?:string[]}) {
  const enabled = options?.enabled ?? true;
  const activeMs = options?.activeMs ?? DEFAULT_ACTIVE_MS;
  const idleMs = options?.idleMs ?? DEFAULT_IDLE_MS;
  const stopWhenTerminal = options?.stopWhenTerminal ?? false;
  const watchReferences = options?.watchReferences ?? [];

  const callback = useRef(onUpdate);
  const [running,setRunning] = useState(enabled);

  useEffect(() => { callback.current = onUpdate; }, [onUpdate]);

  useEffect(() => {
    if (!enabled) { setRunning(false); return; }
    let cancelled = false;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let inFlight = false;
    let delay = activeMs;
    let previousVersion: string | null = null;
    let terminal = true;

    const poll = async () => {
      if (cancelled || inFlight) return;
      inFlight = true;
      try {
        const response = await fetch('/realtime/snapshot', { headers:{Accept:'application/json'}, credentials:'same-origin', cache:'no-store' });
        if (!response.ok) throw new Error('realtime request failed');
        const snapshot: Snapshot = await response.json();
        if (cancelled) return;
        if (snapshot.version !== previousVersion) {
          callback.current(snapshot);
          previousVersion = snapshot.version;
        }
        const watched = watchReferences.length > 0
          ? snapshot.transactions.filter(tx => watchReferences.includes(tx.reference) || watchReferences.includes(tx.uuid))
          : snapshot.transactions;
        terminal = watched.length === 0 ? watchReferences.length === 0 : watched.every(tx => tx.terminal);
        if (stopWhenTerminal && terminal) { setRunning(false); return; }
        delay = terminal ? idleMs : activeMs;
        setRunning(true);
      } catch {
        delay = Math.min(Math.max(delay * 2, idleMs), 60000);
        setRunning(false);
      } finally {
        inFlight = false;
        if (!cancelled) timer = setTimeout(poll, delay);
      }
    };

    poll();
    return () => { cancelled = true; if (timer) clearTimeout(timer); };
  }, [enabled, activeMs, idleMs]);

  return { running };
}
