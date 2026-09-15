export type PrintJobStatus = 'pending' | 'printing' | 'printed' | 'error';

export type PrintJob = {
    id: number;
    original_filename: string;
    copies: number;
    duplex: string;
    duplex_label: string;
    color_mode: string;
    color_mode_label: string;
    page_count: number | null;
    pages_printed: number | null;
    status: PrintJobStatus;
    status_label: string;
    error_message: string | null;
    /** Pourquoi la tâche n’est pas encore partie, quand l’imprimante est bloquée. */
    blocked_reason: string | null;
    is_duplicate: boolean;
    counts_pages: boolean;
    file_exists: boolean;
    created_at: string | null;
    printed_at: string | null;
    user?: { id: number; name: string };
};

export type PrintChoice = {
    value: string;
    label: string;
};

export type PrintOptions = {
    duplex: PrintChoice[];
    colorModes: PrintChoice[];
    maxCopies: number;
    maxFileSizeMb: number;
    /** Avertissement à afficher quand l’imprimante est bloquée, sinon null. */
    printerNotice: string | null;
};
