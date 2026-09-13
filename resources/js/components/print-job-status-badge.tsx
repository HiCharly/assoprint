import { Badge } from '@/components/ui/badge';
import type { PrintJobStatus } from '@/types';

const variants: Record<
    PrintJobStatus,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    pending: 'outline',
    printing: 'secondary',
    printed: 'default',
    error: 'destructive',
};

export default function PrintJobStatusBadge({
    status,
    label,
}: {
    status: PrintJobStatus;
    label: string;
}) {
    return <Badge variant={variants[status]}>{label}</Badge>;
}
