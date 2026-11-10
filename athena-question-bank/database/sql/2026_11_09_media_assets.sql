-- =====================================================================
-- ATHENA Question Bank — Fase 2.12b: gestión de recursos visuales
-- Cambios autorizados: tabla media_assets + columna source_page.
-- No modifica ni elimina filas existentes. No altera estados.
-- =====================================================================

-- 1) Página de origen (nullable; metadato aditivo, sin default ni backfill)
ALTER TABLE public.stimuli
    ADD COLUMN source_page smallint NULL,
    ADD CONSTRAINT stimuli_source_page_check CHECK (source_page IS NULL OR source_page > 0);

ALTER TABLE public.questions
    ADD COLUMN source_page smallint NULL,
    ADD CONSTRAINT questions_source_page_check CHECK (source_page IS NULL OR source_page > 0);

-- 2) Recursos visuales extraídos de un PDF
CREATE TABLE public.media_assets (
    id                 uuid         PRIMARY KEY DEFAULT gen_random_uuid(),
    source_document_id uuid         NOT NULL
        REFERENCES public.source_documents(id) ON DELETE RESTRICT,
    storage_path       varchar(500) NOT NULL,             -- ruta dentro del bucket icfes-media
    page_number        smallint     NOT NULL,
    width              integer      NOT NULL,
    height             integer      NOT NULL,
    mime_type          varchar(100) NOT NULL,
    sha256             char(64)     NOT NULL,
    stimulus_id        uuid NULL
        REFERENCES public.stimuli(id)   ON DELETE RESTRICT,
    question_id        uuid NULL
        REFERENCES public.questions(id) ON DELETE RESTRICT,
    association_status varchar(20)  NOT NULL DEFAULT 'pending',
    associated_at      timestamptz  NULL,
    created_at         timestamptz  NOT NULL DEFAULT now(),
    updated_at         timestamptz  NOT NULL DEFAULT now(),

    CONSTRAINT media_assets_storage_path_key UNIQUE (storage_path),
    CONSTRAINT media_assets_page_check   CHECK (page_number > 0),
    CONSTRAINT media_assets_size_check   CHECK (width > 0 AND height > 0),
    CONSTRAINT media_assets_mime_check   CHECK (mime_type IN ('image/jpeg', 'image/png')),
    CONSTRAINT media_assets_sha256_check CHECK (sha256 ~ '^[0-9a-f]{64}$'),
    CONSTRAINT media_assets_status_check CHECK (association_status IN ('pending', 'confirmed')),
    -- Un recurso se asocia a UN solo destino (estímulo o pregunta), nunca a ambos
    CONSTRAINT media_assets_single_target_check CHECK (stimulus_id IS NULL OR question_id IS NULL),
    -- Coherencia estado <-> destino
    CONSTRAINT media_assets_status_target_check CHECK (
        (association_status = 'pending'
            AND stimulus_id IS NULL AND question_id IS NULL AND associated_at IS NULL)
        OR
        (association_status = 'confirmed'
            AND (stimulus_id IS NOT NULL OR question_id IS NOT NULL) AND associated_at IS NOT NULL)
    )
);

CREATE INDEX idx_media_assets_document ON public.media_assets (source_document_id, page_number);
CREATE INDEX idx_media_assets_stimulus ON public.media_assets (stimulus_id) WHERE stimulus_id IS NOT NULL;
CREATE INDEX idx_media_assets_question ON public.media_assets (question_id) WHERE question_id IS NOT NULL;
CREATE INDEX idx_media_assets_sha256   ON public.media_assets (sha256);

-- 3) El recurso solo puede asociarse a preguntas/estímulos de SU MISMO documento
CREATE FUNCTION public.media_assets_enforce_document_match() RETURNS trigger
LANGUAGE plpgsql
SET search_path = public
AS $$
DECLARE
    target_doc uuid;
BEGIN
    IF NEW.stimulus_id IS NOT NULL THEN
        SELECT source_document_id INTO target_doc FROM public.stimuli WHERE id = NEW.stimulus_id;
        IF target_doc IS DISTINCT FROM NEW.source_document_id THEN
            RAISE EXCEPTION 'El estímulo % no pertenece al documento de origen del recurso %.',
                NEW.stimulus_id, NEW.source_document_id USING ERRCODE = '23514';
        END IF;
    END IF;

    IF NEW.question_id IS NOT NULL THEN
        SELECT source_document_id INTO target_doc FROM public.questions WHERE id = NEW.question_id;
        IF target_doc IS DISTINCT FROM NEW.source_document_id THEN
            RAISE EXCEPTION 'La pregunta % no pertenece al documento de origen del recurso %.',
                NEW.question_id, NEW.source_document_id USING ERRCODE = '23514';
        END IF;
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_media_assets_document_match
    BEFORE INSERT OR UPDATE OF source_document_id, stimulus_id, question_id
    ON public.media_assets
    FOR EACH ROW EXECUTE FUNCTION public.media_assets_enforce_document_match();

-- 4) Mismo criterio de seguridad que el resto de tablas del banco: RLS activo, sin políticas
ALTER TABLE public.media_assets ENABLE ROW LEVEL SECURITY;

