--
-- PostgreSQL database dump
--

-- Dumped from database version 9.3.17
-- Dumped by pg_dump version 9.6.14

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


--
-- Name: track_num_episodes(integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.track_num_episodes(integer) RETURNS boolean
    LANGUAGE plpgsql
    AS $_$ BEGIN
RETURN COUNT(1) > 0 FROM episodes e WHERE e.track_id = $1; END; $_$;


--
-- Name: track_total_length_of_episodes(integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.track_total_length_of_episodes(integer) RETURNS double precision
    LANGUAGE sql
    AS $_$
SELECT DISTINCT t.length FROM tracks t JOIN episodes e ON e.track_id = t.id AND t.id = $1 $_$;


SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: audio; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.audio (
    id integer NOT NULL,
    track_id integer,
    ix integer,
    langcode character varying(255) DEFAULT ''::character varying NOT NULL,
    format character varying(255) DEFAULT ''::character varying NOT NULL,
    channels smallint DEFAULT 0 NOT NULL,
    streamid character varying(255) DEFAULT ''::character varying NOT NULL,
    active smallint
);


--
-- Name: TABLE audio; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.audio IS 'Metadata';


--
-- Name: audio_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.audio_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: audio_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.audio_id_seq OWNED BY public.audio.id;


--
-- Name: blurays; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.blurays (
    id integer NOT NULL,
    dvd_id integer,
    disc_title character varying(255),
    disc_id character varying(40) DEFAULT ''::character varying NOT NULL
);


--
-- Name: COLUMN blurays.disc_title; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.blurays.disc_title IS 'Disc title is optional on Blu-rays';


--
-- Name: COLUMN blurays.disc_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.blurays.disc_id IS 'AACS id';


--
-- Name: blurays_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.blurays_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: blurays_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.blurays_id_seq OWNED BY public.blurays.id;


--
-- Name: bugs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.bugs (
    id integer NOT NULL,
    disc smallint DEFAULT 0 NOT NULL,
    name character varying(255) DEFAULT ''::character varying NOT NULL
);


--
-- Name: bugs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.bugs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: bugs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.bugs_id_seq OWNED BY public.bugs.id;


--
-- Name: cells; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cells (
    id integer NOT NULL,
    track_id integer,
    ix integer DEFAULT 1 NOT NULL,
    length double precision DEFAULT 0 NOT NULL,
    first_sector integer,
    last_sector integer
);


--
-- Name: cells_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cells_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cells_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cells_id_seq OWNED BY public.cells.id;


--
-- Name: chapters; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.chapters (
    id integer NOT NULL,
    track_id integer,
    ix integer,
    length double precision,
    filesize bigint
);


--
-- Name: TABLE chapters; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.chapters IS 'Metadata';


--
-- Name: COLUMN chapters.filesize; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.chapters.filesize IS 'Size in bytes';


--
-- Name: chapters_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.chapters_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: chapters_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.chapters_id_seq OWNED BY public.chapters.id;


--
-- Name: collections; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.collections (
    id integer NOT NULL,
    title character varying(255) DEFAULT ''::character varying NOT NULL
);


--
-- Name: TABLE collections; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.collections IS 'Collection data';


--
-- Name: collections_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.collections_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: collections_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.collections_id_seq OWNED BY public.collections.id;


--
-- Name: dvds; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dvds (
    id integer NOT NULL,
    dvdread_id character varying(255) DEFAULT ''::character varying NOT NULL,
    title character varying(255) DEFAULT ''::character varying NOT NULL,
    filesize bigint,
    metadata_spec smallint DEFAULT 0 NOT NULL,
    side smallint,
    skip smallint DEFAULT 0 NOT NULL,
    notes character varying DEFAULT ''::character varying NOT NULL,
    bluray smallint DEFAULT 0 NOT NULL,
    package_title character varying(255) DEFAULT ''::character varying NOT NULL
);


--
-- Name: TABLE dvds; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.dvds IS 'Metadata';


--
-- Name: COLUMN dvds.filesize; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.dvds.filesize IS 'Size in megabytes';


--
-- Name: tracks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tracks (
    id integer NOT NULL,
    dvd_id integer,
    ix integer DEFAULT 1 NOT NULL,
    length double precision,
    format character varying(255) DEFAULT 'NTSC'::character varying NOT NULL,
    aspect character varying(255) DEFAULT ''::character varying NOT NULL,
    closed_captioning smallint,
    valid smallint DEFAULT 1 NOT NULL,
    codec character varying(5) DEFAULT 'mpeg2'::character varying NOT NULL,
    resolution character varying(5) DEFAULT ''::character varying NOT NULL,
    filesize bigint,
    audio_ix smallint,
    subp_ix smallint,
    vts smallint,
    ttn smallint,
    fps double precision,
    dvdnav smallint DEFAULT 0 NOT NULL
);


--
-- Name: TABLE tracks; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.tracks IS 'Metadata';


--
-- Name: COLUMN tracks.ix; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.tracks.ix IS 'Track number';


--
-- Name: COLUMN tracks.codec; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.tracks.codec IS 'Video codec';


--
-- Name: COLUMN tracks.resolution; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.tracks.resolution IS 'Video format';


--
-- Name: COLUMN tracks.filesize; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.tracks.filesize IS 'Size in bytes';


--
-- Name: dart_audio_tracks; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.dart_audio_tracks AS
 SELECT a.id,
    d.id AS dvd_id,
    t.id AS track_id,
    d.dvdread_id,
    d.title,
    d.filesize,
    d.metadata_spec,
    d.side,
    t.ix AS track_ix,
    t.length,
    t.format AS track_format,
    t.aspect,
    t.closed_captioning,
    t.valid,
    a.ix,
    a.langcode,
    a.format,
    a.channels,
    a.streamid,
    a.active
   FROM ((public.dvds d
     JOIN public.tracks t ON ((t.dvd_id = d.id)))
     JOIN public.audio a ON ((a.track_id = t.id)))
  ORDER BY d.id, t.id, a.id;


--
-- Name: episodes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.episodes_id_seq
    START WITH 14147
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: episodes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.episodes (
    id integer DEFAULT nextval('public.episodes_id_seq'::regclass) NOT NULL,
    track_id integer,
    ix smallint DEFAULT 0 NOT NULL,
    title character varying(255) DEFAULT ''::character varying NOT NULL,
    part smallint,
    starting_chapter smallint,
    ending_chapter smallint,
    season smallint DEFAULT 0 NOT NULL,
    episode_number smallint,
    skip smallint DEFAULT 0 NOT NULL,
    progressive integer,
    top_field integer,
    bottom_field integer,
    crop character varying(255) DEFAULT ''::character varying NOT NULL,
    avcinfo character varying(255) DEFAULT ''::character varying NOT NULL
);


--
-- Name: COLUMN episodes.episode_number; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.episodes.episode_number IS 'Override episode number';


--
-- Name: dart_episodes; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.dart_episodes AS
 SELECT e.id,
    d.id AS dvd_id,
    t.id AS track_id,
    d.dvdread_id,
    d.title AS dvd_title,
    d.filesize,
    d.metadata_spec,
    d.side,
    t.ix AS track_ix,
    t.length AS track_length,
    t.format,
    t.aspect,
    t.closed_captioning,
    t.valid,
    e.ix,
    e.title,
    e.part,
    e.starting_chapter,
    e.ending_chapter,
    e.season
   FROM ((public.dvds d
     JOIN public.tracks t ON ((t.dvd_id = d.id)))
     JOIN public.episodes e ON ((e.track_id = t.id)))
  ORDER BY d.id, t.id, e.id;


--
-- Name: series; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.series (
    id integer NOT NULL,
    collection_id integer,
    title character varying(255) DEFAULT ''::character varying NOT NULL,
    production_year character varying(4) DEFAULT ''::character varying NOT NULL,
    average_length integer DEFAULT 0 NOT NULL,
    grayscale smallint DEFAULT 0 NOT NULL,
    nsix character varying(255) DEFAULT ''::character varying NOT NULL,
    qa_notes text DEFAULT ''::text NOT NULL,
    dvdnav smallint DEFAULT 1 NOT NULL,
    tvdb character varying(255) DEFAULT ''::character varying NOT NULL,
    library_id integer,
    upgrade_id integer,
    active smallint DEFAULT 1 NOT NULL,
    crf smallint,
    decomb smallint DEFAULT 0 NOT NULL,
    detelecine smallint DEFAULT 0 NOT NULL,
    screenshots character varying(255) DEFAULT ''::character varying NOT NULL,
    start_date date
);


--
-- Name: TABLE series; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.series IS 'Collection data';


--
-- Name: COLUMN series.title; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.series.title IS 'Title used for displays everywhere';


--
-- Name: COLUMN series.average_length; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.series.average_length IS 'Average length of minutes of an episode';


--
-- Name: COLUMN series.nsix; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.series.nsix IS 'NSIX - Naming Scheme Index';


--
-- Name: dart_series; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.dart_series AS
 SELECT s.id,
    s.collection_id,
    c.title AS collection_title,
    s.title,
    s.average_length,
    s.grayscale
   FROM (public.series s
     LEFT JOIN public.collections c ON ((c.id = s.collection_id)))
  ORDER BY c.title, s.title;


--
-- Name: series_dvds; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.series_dvds (
    id integer NOT NULL,
    series_id integer,
    dvd_id integer,
    side character(1) DEFAULT ' '::bpchar NOT NULL,
    season smallint DEFAULT 0 NOT NULL,
    ix smallint DEFAULT 1 NOT NULL,
    volume smallint DEFAULT 0 NOT NULL,
    audio_preference smallint DEFAULT 0 NOT NULL
);


--
-- Name: TABLE series_dvds; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.series_dvds IS 'Collection data';


--
-- Name: COLUMN series_dvds.season; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.series_dvds.season IS 'Default season for episodes';


--
-- Name: COLUMN series_dvds.audio_preference; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.series_dvds.audio_preference IS 'Which audio track to select by default';


--
-- Name: dart_series_dvds; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.dart_series_dvds AS
 SELECT sd.id,
    s.collection_id,
    d.id AS dvd_id,
    s.id AS series_id,
    c.title AS collection_title,
    s.title AS series_title,
    s.average_length,
    s.grayscale,
    sd.side AS series_dvds_side,
    sd.season AS series_dvds_season,
    sd.ix,
    sd.volume,
    sd.audio_preference,
    d.dvdread_id,
    d.title AS dvd_title,
    d.filesize,
    d.metadata_spec,
    d.side
   FROM (((public.series_dvds sd
     JOIN public.series s ON ((s.id = sd.series_id)))
     JOIN public.dvds d ON ((sd.dvd_id = d.id)))
     LEFT JOIN public.collections c ON ((c.id = s.collection_id)))
  ORDER BY c.title, s.title, sd.ix;


--
-- Name: dart_series_episodes; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.dart_series_episodes AS
 SELECT e.id,
    s.collection_id,
    d.id AS dvd_id,
    s.id AS series_id,
    sd.id AS series_dvds_id,
    t.id AS track_id,
    c.title AS collection_title,
    s.title AS series_title,
    s.nsix,
    s.average_length,
    s.grayscale,
    s.production_year,
    sd.side AS series_dvds_side,
    sd.ix AS series_dvds_ix,
    sd.volume,
    sd.audio_preference,
    d.dvdread_id,
    d.title AS dvd_title,
    d.filesize,
    d.metadata_spec,
    d.side,
    t.ix AS track_ix,
    t.length,
    t.format,
    t.aspect,
    t.closed_captioning,
    t.valid,
    e.ix,
    e.title,
    e.part,
    e.starting_chapter,
    e.ending_chapter,
    e.episode_number,
    COALESCE(NULLIF(e.season, 0), sd.season) AS season
   FROM (((((public.episodes e
     JOIN public.tracks t ON ((e.track_id = t.id)))
     JOIN public.dvds d ON ((d.id = t.dvd_id)))
     JOIN public.series_dvds sd ON ((sd.dvd_id = d.id)))
     JOIN public.series s ON ((sd.series_id = s.id)))
     JOIN public.collections c ON ((c.id = s.collection_id)))
  ORDER BY c.title, s.title, sd.ix, t.ix, e.ix, e.id;


--
-- Name: dart_series_tracks; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.dart_series_tracks AS
 SELECT t.id,
    s.collection_id,
    d.id AS dvd_id,
    s.id AS series_id,
    sd.id AS series_dvds_id,
    c.title AS collection_title,
    s.title AS series_title,
    s.average_length,
    s.grayscale,
    sd.side AS series_dvds_side,
    sd.season AS series_dvds_season,
    sd.ix AS series_dvds_ix,
    sd.volume,
    sd.audio_preference,
    d.dvdread_id,
    d.title AS dvd_title,
    d.filesize,
    d.metadata_spec,
    d.side,
    t.ix,
    t.length,
    t.format,
    t.aspect,
    t.closed_captioning,
    t.valid
   FROM ((((public.tracks t
     JOIN public.dvds d ON ((d.id = t.dvd_id)))
     JOIN public.series_dvds sd ON ((sd.dvd_id = d.id)))
     JOIN public.series s ON ((sd.series_id = s.id)))
     JOIN public.collections c ON ((c.id = s.collection_id)))
  ORDER BY c.title, s.title, sd.ix, t.ix;


--
-- Name: subp; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.subp (
    id integer NOT NULL,
    track_id integer,
    ix integer,
    langcode character varying(255) DEFAULT ''::character varying NOT NULL,
    streamid character varying(255) DEFAULT ''::character varying NOT NULL,
    active smallint,
    passthrough smallint,
    format character varying(6) DEFAULT ''::character varying NOT NULL
);


--
-- Name: TABLE subp; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.subp IS 'Metadata';


--
-- Name: dart_subp_tracks; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.dart_subp_tracks AS
 SELECT s.id,
    d.id AS dvd_id,
    t.id AS track_id,
    d.dvdread_id,
    d.title,
    d.filesize,
    d.metadata_spec,
    d.side,
    t.ix AS track_ix,
    t.length,
    t.format AS track_format,
    t.aspect,
    t.closed_captioning,
    t.valid,
    s.ix,
    s.langcode,
    s.streamid,
    s.active
   FROM ((public.dvds d
     JOIN public.tracks t ON ((t.dvd_id = d.id)))
     JOIN public.subp s ON ((s.track_id = t.id)))
  ORDER BY d.id, t.id, s.id;


--
-- Name: dart_tracks; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.dart_tracks AS
 SELECT t.id,
    d.id AS dvd_id,
    d.dvdread_id,
    d.title,
    d.filesize,
    d.metadata_spec,
    d.side,
    t.ix,
    t.length,
    t.format,
    t.aspect,
    t.closed_captioning,
    t.valid
   FROM (public.dvds d
     JOIN public.tracks t ON ((t.dvd_id = d.id)))
  ORDER BY d.id, t.id;


--
-- Name: dvd_bugs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dvd_bugs (
    id integer NOT NULL,
    dvd_id integer NOT NULL,
    bug_id integer NOT NULL
);


--
-- Name: dvd_bugs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dvd_bugs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dvd_bugs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dvd_bugs_id_seq OWNED BY public.dvd_bugs.id;


--
-- Name: dvds_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dvds_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dvds_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dvds_id_seq OWNED BY public.dvds.id;


--
-- Name: libraries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.libraries (
    id integer NOT NULL,
    collection_id integer DEFAULT 1 NOT NULL,
    name character varying(255) NOT NULL,
    plex_dir character varying(255) DEFAULT ''::character varying NOT NULL
);


--
-- Name: libraries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.libraries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: libraries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.libraries_id_seq OWNED BY public.libraries.id;


--
-- Name: presets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.presets (
    id integer NOT NULL,
    name character varying(255) DEFAULT ''::character varying NOT NULL,
    crf integer DEFAULT 20,
    format character varying DEFAULT 'mkv'::character varying NOT NULL,
    acodec character varying DEFAULT 'copy'::character varying NOT NULL,
    x264_preset character varying(255) DEFAULT 'medium'::character varying NOT NULL,
    x264_tune character varying(255) DEFAULT ''::character varying NOT NULL,
    deinterlace smallint DEFAULT 0 NOT NULL,
    decomb smallint DEFAULT 0 NOT NULL,
    detelecine smallint DEFAULT 0 NOT NULL,
    fps smallint DEFAULT 60,
    vcodec character varying(4) DEFAULT 'avc'::bpchar NOT NULL
);


--
-- Name: TABLE presets; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.presets IS 'Handbrake';


--
-- Name: presets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.presets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: presets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.presets_id_seq OWNED BY public.presets.id;


--
-- Name: qa_audio_channel_priority; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.qa_audio_channel_priority AS
 SELECT at1.track_id,
    at1.langcode,
    at1.format,
    at1.streamid AS streamid_1,
    at2.streamid AS streamid_2,
    at1.channels AS channels_1,
    at2.channels AS channels_2
   FROM (public.audio at1
     JOIN public.audio at2 ON ((((((((((at1.track_id = at2.track_id) AND ((at1.langcode)::text = (at2.langcode)::text)) AND ((at1.streamid)::text <> (at2.streamid)::text)) AND (at1.active = 1)) AND (at2.active = 1)) AND ((at1.format)::text = (at2.format)::text)) AND (at2.channels > at1.channels)) AND (at2.ix > at1.ix)) AND (length((at1.langcode)::text) = 2))))
  ORDER BY at1.track_id;


--
-- Name: qa_bluray_best_audio; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.qa_bluray_best_audio AS
 SELECT audio.id,
    audio.track_id,
    audio.ix,
    audio.format,
    audio.langcode
   FROM public.audio
  WHERE (audio.track_id IN ( SELECT audio_1.track_id
           FROM public.audio audio_1
          WHERE (((audio_1.format)::text = ANY (ARRAY[('lpcm'::character varying)::text, ('dtshd-ma'::character varying)::text, ('truhd'::character varying)::text, ('dtshd'::character varying)::text])) AND (audio_1.ix > 1))))
  ORDER BY audio.track_id, audio.ix;


--
-- Name: series_dvds_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.series_dvds_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: series_dvds_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.series_dvds_id_seq OWNED BY public.series_dvds.id;


--
-- Name: series_presets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.series_presets (
    id integer NOT NULL,
    series_id integer,
    preset_id integer DEFAULT 43
);


--
-- Name: TABLE series_presets; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE public.series_presets IS 'Handbrake';


--
-- Name: series_presets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.series_presets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: series_presets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.series_presets_id_seq OWNED BY public.series_presets.id;


--
-- Name: sets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sets_id_seq OWNED BY public.series.id;


--
-- Name: specs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.specs (
    id integer NOT NULL,
    metadata character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    version double precision DEFAULT 0 NOT NULL
);


--
-- Name: specs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.specs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: specs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.specs_id_seq OWNED BY public.specs.id;


--
-- Name: subp_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.subp_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: subp_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.subp_id_seq OWNED BY public.subp.id;


--
-- Name: tracks_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tracks_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tracks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tracks_id_seq OWNED BY public.tracks.id;


--
-- Name: view_dvd_nsix; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.view_dvd_nsix AS
 SELECT s.collection_id,
    sd.series_id,
    sd.dvd_id,
    s.nsix,
    ((((((((s.collection_id || '.'::text) || to_char(sd.series_id, 'FM000'::text)) || '.'::text) || to_char(sd.dvd_id, 'FM0000'::text)) || '.'::text) || (s.nsix)::text) || '.'::text) || 'iso'::text) AS dvd_nsix_iso,
    s.title AS series_title
   FROM (public.series s
     JOIN public.series_dvds sd ON ((sd.series_id = s.id)));


--
-- Name: view_episode_frames; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.view_episode_frames AS
 SELECT e.id,
    ((((((((s.collection_id || '.'::text) || to_char(sd.series_id, 'FM000'::text)) || '.'::text) || to_char(sd.dvd_id, 'FM0000'::text)) || '.'::text) || to_char(e.id, 'FM00000'::text)) || '.'::text) || (s.nsix)::text) AS episode_nsix,
    e.skip,
    e.title,
    e.progressive,
    e.top_field AS tff,
    e.bottom_field AS bff
   FROM (((((public.episodes e
     JOIN public.tracks t ON ((e.track_id = t.id)))
     JOIN public.dvds d ON ((d.id = t.dvd_id)))
     JOIN public.series_dvds sd ON ((sd.dvd_id = d.id)))
     JOIN public.series s ON ((sd.series_id = s.id)))
     JOIN public.collections c ON ((c.id = s.collection_id)))
  ORDER BY s.collection_id, s.id, d.id, e.id;


--
-- Name: view_episode_library; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.view_episode_library AS
 SELECT e.id,
    ((((((((s.collection_id || '.'::text) || to_char(sd.series_id, 'FM000'::text)) || '.'::text) || to_char(sd.dvd_id, 'FM0000'::text)) || '.'::text) || to_char(e.id, 'FM00000'::text)) || '.'::text) || (s.nsix)::text) AS episode_nsix,
    e.skip,
    s.library_id,
    s.upgrade_id,
    e.title
   FROM (((((public.episodes e
     JOIN public.tracks t ON ((e.track_id = t.id)))
     JOIN public.dvds d ON ((d.id = t.dvd_id)))
     JOIN public.series_dvds sd ON ((sd.dvd_id = d.id)))
     JOIN public.series s ON ((sd.series_id = s.id)))
     JOIN public.collections c ON ((c.id = s.collection_id)))
  ORDER BY s.collection_id, s.id, d.id, e.id;


--
-- Name: view_episode_nsix; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.view_episode_nsix AS
 SELECT e.id,
    ((((((((s.collection_id || '.'::text) || to_char(sd.series_id, 'FM000'::text)) || '.'::text) || to_char(sd.dvd_id, 'FM0000'::text)) || '.'::text) || to_char(e.id, 'FM00000'::text)) || '.'::text) || (s.nsix)::text) AS episode_nsix,
    e.skip,
    e.title
   FROM (((((public.episodes e
     JOIN public.tracks t ON ((e.track_id = t.id)))
     JOIN public.dvds d ON ((d.id = t.dvd_id)))
     JOIN public.series_dvds sd ON ((sd.dvd_id = d.id)))
     JOIN public.series s ON ((sd.series_id = s.id)))
     JOIN public.collections c ON ((c.id = s.collection_id)))
  ORDER BY s.collection_id, s.id, d.id, e.id;


--
-- Name: view_episodes; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.view_episodes AS
 SELECT e.id AS episode_id,
    e.track_id,
    e.ix AS episode_ix,
    e.title AS episode_title,
    e.part AS episode_part,
    e.starting_chapter AS episode_starting_chapter,
    e.ending_chapter AS episode_ending_chapter,
    e.season AS episode_season,
    e.episode_number,
    e.skip AS episode_skip,
    e.progressive,
    e.top_field,
    e.bottom_field,
    e.crop,
    t.dvd_id,
    d.bluray,
    t.ix AS track_ix,
    t.length AS track_length,
    t.closed_captioning,
    s.collection_id,
    s.id AS series_id,
    s.title AS series_title,
    sd.id AS series_dvds_id,
    sd.side AS series_dvds_side,
    sd.season AS series_dvds_season,
    sd.ix AS series_dvds_ix,
    sd.volume AS series_dvds_volume,
    COALESCE(NULLIF(e.season, 0), sd.season) AS season
   FROM ((((public.episodes e
     JOIN public.tracks t ON ((e.track_id = t.id)))
     JOIN public.dvds d ON ((t.dvd_id = d.id)))
     JOIN public.series_dvds sd ON ((sd.dvd_id = d.id)))
     JOIN public.series s ON ((s.id = sd.series_id)));


--
-- Name: audio id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audio ALTER COLUMN id SET DEFAULT nextval('public.audio_id_seq'::regclass);


--
-- Name: blurays id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blurays ALTER COLUMN id SET DEFAULT nextval('public.blurays_id_seq'::regclass);


--
-- Name: bugs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bugs ALTER COLUMN id SET DEFAULT nextval('public.bugs_id_seq'::regclass);


--
-- Name: cells id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cells ALTER COLUMN id SET DEFAULT nextval('public.cells_id_seq'::regclass);


--
-- Name: chapters id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chapters ALTER COLUMN id SET DEFAULT nextval('public.chapters_id_seq'::regclass);


--
-- Name: collections id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.collections ALTER COLUMN id SET DEFAULT nextval('public.collections_id_seq'::regclass);


--
-- Name: dvd_bugs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dvd_bugs ALTER COLUMN id SET DEFAULT nextval('public.dvd_bugs_id_seq'::regclass);


--
-- Name: dvds id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dvds ALTER COLUMN id SET DEFAULT nextval('public.dvds_id_seq'::regclass);


--
-- Name: libraries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.libraries ALTER COLUMN id SET DEFAULT nextval('public.libraries_id_seq'::regclass);


--
-- Name: presets id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.presets ALTER COLUMN id SET DEFAULT nextval('public.presets_id_seq'::regclass);


--
-- Name: series id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.series ALTER COLUMN id SET DEFAULT nextval('public.sets_id_seq'::regclass);


--
-- Name: series_dvds id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.series_dvds ALTER COLUMN id SET DEFAULT nextval('public.series_dvds_id_seq'::regclass);


--
-- Name: series_presets id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.series_presets ALTER COLUMN id SET DEFAULT nextval('public.series_presets_id_seq'::regclass);


--
-- Name: specs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.specs ALTER COLUMN id SET DEFAULT nextval('public.specs_id_seq'::regclass);


--
-- Name: subp id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subp ALTER COLUMN id SET DEFAULT nextval('public.subp_id_seq'::regclass);


--
-- Name: tracks id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tracks ALTER COLUMN id SET DEFAULT nextval('public.tracks_id_seq'::regclass);


--
-- Name: audio audio_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audio
    ADD CONSTRAINT audio_pkey PRIMARY KEY (id);

ALTER TABLE public.audio CLUSTER ON audio_pkey;


--
-- Name: blurays blurays_dvd_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blurays
    ADD CONSTRAINT blurays_dvd_id_key UNIQUE (dvd_id);


--
-- Name: blurays blurays_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blurays
    ADD CONSTRAINT blurays_pkey PRIMARY KEY (id);


--
-- Name: bugs bugs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bugs
    ADD CONSTRAINT bugs_pkey PRIMARY KEY (id);


--
-- Name: cells cells_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cells
    ADD CONSTRAINT cells_pkey PRIMARY KEY (id);


--
-- Name: chapters chapters_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chapters
    ADD CONSTRAINT chapters_pkey PRIMARY KEY (id);

ALTER TABLE public.chapters CLUSTER ON chapters_pkey;


--
-- Name: collections collections_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.collections
    ADD CONSTRAINT collections_pkey PRIMARY KEY (id);


--
-- Name: dvd_bugs dvd_bugs_dvd_id_bug_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dvd_bugs
    ADD CONSTRAINT dvd_bugs_dvd_id_bug_id_key UNIQUE (dvd_id, bug_id);


--
-- Name: dvd_bugs dvd_bugs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dvd_bugs
    ADD CONSTRAINT dvd_bugs_pkey PRIMARY KEY (id);


--
-- Name: dvds dvds_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dvds
    ADD CONSTRAINT dvds_pkey PRIMARY KEY (id);

ALTER TABLE public.dvds CLUSTER ON dvds_pkey;


--
-- Name: episodes episodes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.episodes
    ADD CONSTRAINT episodes_pkey PRIMARY KEY (id);

ALTER TABLE public.episodes CLUSTER ON episodes_pkey;


--
-- Name: libraries libraries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.libraries
    ADD CONSTRAINT libraries_pkey PRIMARY KEY (id);


--
-- Name: presets presets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.presets
    ADD CONSTRAINT presets_pkey PRIMARY KEY (id);

ALTER TABLE public.presets CLUSTER ON presets_pkey;


--
-- Name: series_dvds series_dvds_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.series_dvds
    ADD CONSTRAINT series_dvds_pkey PRIMARY KEY (id);


--
-- Name: series series_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.series
    ADD CONSTRAINT series_pkey PRIMARY KEY (id);


--
-- Name: series_presets series_presets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.series_presets
    ADD CONSTRAINT series_presets_pkey PRIMARY KEY (id);

ALTER TABLE public.series_presets CLUSTER ON series_presets_pkey;


--
-- Name: specs specs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.specs
    ADD CONSTRAINT specs_pkey PRIMARY KEY (id);

ALTER TABLE public.specs CLUSTER ON specs_pkey;


--
-- Name: subp subp_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subp
    ADD CONSTRAINT subp_pkey PRIMARY KEY (id);

ALTER TABLE public.subp CLUSTER ON subp_pkey;


--
-- Name: tracks tracks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tracks
    ADD CONSTRAINT tracks_pkey PRIMARY KEY (id);

ALTER TABLE public.tracks CLUSTER ON tracks_pkey;


--
-- Name: audio audio_track_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audio
    ADD CONSTRAINT audio_track_id_fkey FOREIGN KEY (track_id) REFERENCES public.tracks(id) ON DELETE CASCADE;


--
-- Name: blurays blurays_dvd_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blurays
    ADD CONSTRAINT blurays_dvd_id_fkey FOREIGN KEY (dvd_id) REFERENCES public.dvds(id) ON DELETE CASCADE;


--
-- Name: cells cells_track_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cells
    ADD CONSTRAINT cells_track_id_fkey FOREIGN KEY (track_id) REFERENCES public.tracks(id) ON DELETE CASCADE;


--
-- Name: chapters chapters_track_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chapters
    ADD CONSTRAINT chapters_track_id_fkey FOREIGN KEY (track_id) REFERENCES public.tracks(id) ON DELETE CASCADE;


--
-- Name: dvd_bugs dvd_bugs_bug_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dvd_bugs
    ADD CONSTRAINT dvd_bugs_bug_id_fkey FOREIGN KEY (bug_id) REFERENCES public.bugs(id) ON DELETE CASCADE;


--
-- Name: dvd_bugs dvd_bugs_dvd_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dvd_bugs
    ADD CONSTRAINT dvd_bugs_dvd_id_fkey FOREIGN KEY (dvd_id) REFERENCES public.dvds(id) ON DELETE CASCADE;


--
-- Name: episodes episodes_track_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.episodes
    ADD CONSTRAINT episodes_track_id_fkey FOREIGN KEY (track_id) REFERENCES public.tracks(id) ON DELETE CASCADE;


--
-- Name: libraries libraries_collection_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.libraries
    ADD CONSTRAINT libraries_collection_id_fkey FOREIGN KEY (collection_id) REFERENCES public.collections(id) ON DELETE CASCADE;


--
-- Name: series_dvds series_dvds_dvd_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.series_dvds
    ADD CONSTRAINT series_dvds_dvd_id_fkey FOREIGN KEY (dvd_id) REFERENCES public.dvds(id) ON DELETE CASCADE;


--
-- Name: series_dvds series_dvds_series_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.series_dvds
    ADD CONSTRAINT series_dvds_series_id_fkey FOREIGN KEY (series_id) REFERENCES public.series(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: series series_library_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.series
    ADD CONSTRAINT series_library_id_fkey FOREIGN KEY (library_id) REFERENCES public.libraries(id) ON DELETE SET NULL;


--
-- Name: series_presets series_presets_series_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.series_presets
    ADD CONSTRAINT series_presets_series_id_fkey FOREIGN KEY (series_id) REFERENCES public.series(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: series series_upgrade_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.series
    ADD CONSTRAINT series_upgrade_id_fkey FOREIGN KEY (upgrade_id) REFERENCES public.series(id) ON DELETE SET NULL;


--
-- Name: subp subp_track_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subp
    ADD CONSTRAINT subp_track_id_fkey FOREIGN KEY (track_id) REFERENCES public.tracks(id) ON DELETE CASCADE;


--
-- Name: tracks tracks_dvd_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tracks
    ADD CONSTRAINT tracks_dvd_id_fkey FOREIGN KEY (dvd_id) REFERENCES public.dvds(id) ON DELETE CASCADE;


--
-- Name: SCHEMA public; Type: ACL; Schema: -; Owner: -
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--

