--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


SET search_path = public, pg_catalog;

--
-- Name: track_num_episodes(integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION track_num_episodes(integer) RETURNS boolean
    LANGUAGE plpgsql
    AS $_$ BEGIN
RETURN COUNT(1) > 0 FROM episodes e WHERE e.track_id = $1; END; $_$;


--
-- Name: track_total_length_of_episodes(integer); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION track_total_length_of_episodes(integer) RETURNS double precision
    LANGUAGE sql
    AS $_$
SELECT DISTINCT t.length FROM tracks t JOIN episodes e ON e.track_id = t.id AND t.id = $1 $_$;


SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: audio; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE audio (
    id integer NOT NULL,
    track_id integer,
    ix integer,
    langcode character varying(255) DEFAULT ''::character varying NOT NULL,
    format character varying(255) DEFAULT ''::character varying NOT NULL,
    channels smallint,
    streamid character varying(255) DEFAULT ''::character varying NOT NULL,
    active smallint
);


--
-- Name: TABLE audio; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE audio IS 'Metadata';


--
-- Name: audio_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE audio_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: audio_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE audio_id_seq OWNED BY audio.id;


--
-- Name: cells; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE cells (
    id integer NOT NULL,
    track_id integer,
    ix integer,
    length double precision
);


--
-- Name: TABLE cells; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE cells IS 'Metadata';


--
-- Name: cells_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE cells_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cells_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE cells_id_seq OWNED BY cells.id;


--
-- Name: chapters; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE chapters (
    id integer NOT NULL,
    track_id integer,
    ix integer,
    length double precision,
    startcell integer
);


--
-- Name: TABLE chapters; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE chapters IS 'Metadata';


--
-- Name: chapters_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE chapters_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: chapters_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE chapters_id_seq OWNED BY chapters.id;


--
-- Name: collection_sets; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE collection_sets (
    id integer NOT NULL,
    collection_id integer,
    name character varying DEFAULT ''::character varying NOT NULL
);


--
-- Name: collection_sets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE collection_sets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: collection_sets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE collection_sets_id_seq OWNED BY collection_sets.id;


--
-- Name: collections; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE collections (
    id integer NOT NULL,
    title character varying(255) DEFAULT ''::character varying NOT NULL
);


--
-- Name: TABLE collections; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE collections IS 'Collection data';


--
-- Name: collections_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE collections_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: collections_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE collections_id_seq OWNED BY collections.id;


--
-- Name: dvds; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE dvds (
    id integer NOT NULL,
    dvdread_id character varying(255) DEFAULT ''::character varying NOT NULL,
    title character varying(255) DEFAULT ''::character varying NOT NULL,
    filesize bigint,
    metadata_spec smallint DEFAULT 0 NOT NULL,
    side smallint
);


--
-- Name: TABLE dvds; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE dvds IS 'Metadata';


--
-- Name: dvds_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE dvds_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dvds_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE dvds_id_seq OWNED BY dvds.id;


--
-- Name: episodes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE episodes_id_seq
    START WITH 14147
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: episodes; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE episodes (
    id integer DEFAULT nextval('episodes_id_seq'::regclass) NOT NULL,
    track_id integer,
    ix smallint DEFAULT 0 NOT NULL,
    title character varying(255) DEFAULT ''::character varying NOT NULL,
    part smallint,
    starting_chapter smallint,
    ending_chapter smallint,
    season smallint DEFAULT 0 NOT NULL
);


--
-- Name: genres; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE genres (
    id integer NOT NULL,
    name character varying DEFAULT ''::character varying NOT NULL
);


--
-- Name: genres_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE genres_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: genres_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE genres_id_seq OWNED BY genres.id;


--
-- Name: library; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE library (
    id integer NOT NULL,
    name character varying(255) DEFAULT ''::character varying NOT NULL
);


--
-- Name: library_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE library_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: library_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE library_id_seq OWNED BY library.id;


--
-- Name: presets; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE presets (
    id integer NOT NULL,
    name character varying(255) DEFAULT ''::character varying NOT NULL,
    crf integer,
    x264opts character varying(255) DEFAULT ''::character varying NOT NULL,
    format character(3) DEFAULT 'mkv'::bpchar,
    acodec character varying(4) DEFAULT 'copy'::character varying,
    acodec_bitrate smallint DEFAULT 96,
    video_bitrate integer,
    x264_preset character varying(255) DEFAULT ''::character varying NOT NULL,
    x264_tune character varying(255) DEFAULT ''::character varying NOT NULL,
    two_pass boolean DEFAULT false NOT NULL,
    two_pass_turbo boolean DEFAULT false NOT NULL
);


--
-- Name: TABLE presets; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE presets IS 'Handbrake';


--
-- Name: presets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE presets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: presets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE presets_id_seq OWNED BY presets.id;


SET default_with_oids = true;

--
-- Name: queue; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE queue (
    id integer NOT NULL,
    hostname character varying(255) DEFAULT ''::character varying NOT NULL,
    episode_id integer,
    insert_date timestamp with time zone DEFAULT ('now'::text)::timestamp(6) with time zone NOT NULL,
    x264 smallint DEFAULT 0 NOT NULL,
    xml smallint DEFAULT 0 NOT NULL,
    mkv smallint DEFAULT 0 NOT NULL
);


--
-- Name: queue_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE queue_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: queue_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE queue_id_seq OWNED BY queue.id;


SET default_with_oids = false;

--
-- Name: series; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE series (
    id integer NOT NULL,
    collection_id integer,
    title character varying(255) DEFAULT ''::character varying NOT NULL,
    production_year character varying(4) DEFAULT ''::character varying NOT NULL,
    indexed boolean DEFAULT false NOT NULL,
    average_length integer DEFAULT 0 NOT NULL,
    grayscale smallint DEFAULT 0 NOT NULL
);


--
-- Name: TABLE series; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE series IS 'Collection data';


--
-- Name: COLUMN series.title; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN series.title IS 'Title used for displays everywhere';


--
-- Name: COLUMN series.indexed; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN series.indexed IS 'Use indexing or not for episodes';


--
-- Name: COLUMN series.average_length; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN series.average_length IS 'Average length of minutes of an episode';


--
-- Name: series_alt_titles; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE series_alt_titles (
    id integer NOT NULL,
    series_id integer NOT NULL,
    title character varying(255) DEFAULT ''::character varying NOT NULL
);


--
-- Name: series_alt_titles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE series_alt_titles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: series_alt_titles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE series_alt_titles_id_seq OWNED BY series_alt_titles.id;


--
-- Name: series_dvds; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE series_dvds (
    id integer NOT NULL,
    series_id integer,
    dvd_id integer,
    side character(1) DEFAULT ' '::bpchar NOT NULL,
    season smallint DEFAULT 0 NOT NULL,
    ix smallint DEFAULT 1 NOT NULL,
    size bigint,
    volume smallint DEFAULT 0 NOT NULL,
    audio_preference smallint DEFAULT 0 NOT NULL
);


--
-- Name: TABLE series_dvds; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE series_dvds IS 'Collection data';


--
-- Name: COLUMN series_dvds.season; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN series_dvds.season IS 'Default season for episodes';


--
-- Name: COLUMN series_dvds.audio_preference; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN series_dvds.audio_preference IS 'Which audio track to select by default';


--
-- Name: series_dvds_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE series_dvds_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: series_dvds_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE series_dvds_id_seq OWNED BY series_dvds.id;


--
-- Name: series_library; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE series_library (
    id integer NOT NULL,
    series_id integer NOT NULL,
    library_id integer NOT NULL
);


--
-- Name: series_library_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE series_library_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: series_library_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE series_library_id_seq OWNED BY series_library.id;


--
-- Name: series_presets; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE series_presets (
    id integer NOT NULL,
    series_id integer,
    preset_id integer
);


--
-- Name: TABLE series_presets; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE series_presets IS 'Handbrake';


--
-- Name: series_presets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE series_presets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: series_presets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE series_presets_id_seq OWNED BY series_presets.id;


--
-- Name: sets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE sets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE sets_id_seq OWNED BY series.id;


--
-- Name: specs; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE specs (
    id integer NOT NULL,
    metadata character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    version double precision DEFAULT 0 NOT NULL
);


--
-- Name: specs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE specs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: specs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE specs_id_seq OWNED BY specs.id;


--
-- Name: subp; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE subp (
    id integer NOT NULL,
    track_id integer,
    ix integer,
    langcode character varying(255) DEFAULT ''::character varying NOT NULL,
    streamid character varying(255) DEFAULT ''::character varying NOT NULL,
    active smallint
);


--
-- Name: TABLE subp; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE subp IS 'Metadata';


--
-- Name: subp_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE subp_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: subp_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE subp_id_seq OWNED BY subp.id;


--
-- Name: tags; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE tags (
    id integer NOT NULL,
    name character varying(255) DEFAULT ''::character varying NOT NULL,
    description character varying(255) DEFAULT ''::character varying NOT NULL
);


--
-- Name: tags_dvds; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE tags_dvds (
    id integer NOT NULL,
    tag_id integer NOT NULL,
    dvd_id integer NOT NULL
);


--
-- Name: tags_dvds_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE tags_dvds_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tags_dvds_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE tags_dvds_id_seq OWNED BY tags_dvds.id;


--
-- Name: tags_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE tags_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tags_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE tags_id_seq OWNED BY tags.id;


--
-- Name: tags_tracks; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE tags_tracks (
    id integer NOT NULL,
    tag_id integer NOT NULL,
    track_id integer NOT NULL
);


--
-- Name: tags_tracks_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE tags_tracks_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tags_tracks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE tags_tracks_id_seq OWNED BY tags_tracks.id;


--
-- Name: tracks; Type: TABLE; Schema: public; Owner: -; Tablespace: 
--

CREATE TABLE tracks (
    id integer NOT NULL,
    dvd_id integer,
    ix integer DEFAULT 1 NOT NULL,
    length double precision,
    format character varying(255) DEFAULT 'NTSC'::character varying NOT NULL,
    aspect character varying(255) DEFAULT ''::character varying NOT NULL,
    closed_captioning smallint
);


--
-- Name: TABLE tracks; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON TABLE tracks IS 'Metadata';


--
-- Name: COLUMN tracks.ix; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN tracks.ix IS 'Track number';


--
-- Name: tracks_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE tracks_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tracks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE tracks_id_seq OWNED BY tracks.id;


--
-- Name: view_episodes; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW view_episodes AS
 SELECT e.id AS episode_id,
    e.track_id,
    e.ix AS episode_ix,
    e.title AS episode_title,
    e.part AS episode_part,
    e.starting_chapter AS episode_starting_chapter,
    e.ending_chapter AS episode_ending_chapter,
    e.season AS episode_season,
    t.dvd_id,
    t.ix AS track_ix,
    t.length AS track_length,
    t.closed_captioning,
    s.id AS series_id,
    s.title AS series_title,
    sd.id AS series_dvds_id,
    sd.side AS series_dvds_side,
    sd.season AS series_dvds_season,
    sd.ix AS series_dvds_ix,
    sd.volume AS series_dvds_volume,
    GREATEST(e.season, sd.season) AS season
   FROM ((((episodes e
   JOIN tracks t ON ((e.track_id = t.id)))
   JOIN dvds d ON ((t.dvd_id = d.id)))
   JOIN series_dvds sd ON ((sd.dvd_id = d.id)))
   JOIN series s ON ((s.id = sd.series_id)));


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY audio ALTER COLUMN id SET DEFAULT nextval('audio_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY cells ALTER COLUMN id SET DEFAULT nextval('cells_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY chapters ALTER COLUMN id SET DEFAULT nextval('chapters_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY collection_sets ALTER COLUMN id SET DEFAULT nextval('collection_sets_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY collections ALTER COLUMN id SET DEFAULT nextval('collections_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY dvds ALTER COLUMN id SET DEFAULT nextval('dvds_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY genres ALTER COLUMN id SET DEFAULT nextval('genres_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY library ALTER COLUMN id SET DEFAULT nextval('library_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY presets ALTER COLUMN id SET DEFAULT nextval('presets_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY queue ALTER COLUMN id SET DEFAULT nextval('queue_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY series ALTER COLUMN id SET DEFAULT nextval('sets_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY series_alt_titles ALTER COLUMN id SET DEFAULT nextval('series_alt_titles_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY series_dvds ALTER COLUMN id SET DEFAULT nextval('series_dvds_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY series_library ALTER COLUMN id SET DEFAULT nextval('series_library_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY series_presets ALTER COLUMN id SET DEFAULT nextval('series_presets_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY specs ALTER COLUMN id SET DEFAULT nextval('specs_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY subp ALTER COLUMN id SET DEFAULT nextval('subp_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY tags ALTER COLUMN id SET DEFAULT nextval('tags_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY tags_dvds ALTER COLUMN id SET DEFAULT nextval('tags_dvds_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY tags_tracks ALTER COLUMN id SET DEFAULT nextval('tags_tracks_id_seq'::regclass);


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY tracks ALTER COLUMN id SET DEFAULT nextval('tracks_id_seq'::regclass);


--
-- Name: audio_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY audio
    ADD CONSTRAINT audio_pkey PRIMARY KEY (id);

ALTER TABLE audio CLUSTER ON audio_pkey;


--
-- Name: cells_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY cells
    ADD CONSTRAINT cells_pkey PRIMARY KEY (id);

ALTER TABLE cells CLUSTER ON cells_pkey;


--
-- Name: chapters_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY chapters
    ADD CONSTRAINT chapters_pkey PRIMARY KEY (id);

ALTER TABLE chapters CLUSTER ON chapters_pkey;


--
-- Name: collection_sets_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY collection_sets
    ADD CONSTRAINT collection_sets_pkey PRIMARY KEY (id);


--
-- Name: collections_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY collections
    ADD CONSTRAINT collections_pkey PRIMARY KEY (id);


--
-- Name: dvds_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY dvds
    ADD CONSTRAINT dvds_pkey PRIMARY KEY (id);

ALTER TABLE dvds CLUSTER ON dvds_pkey;


--
-- Name: episodes_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY episodes
    ADD CONSTRAINT episodes_pkey PRIMARY KEY (id);

ALTER TABLE episodes CLUSTER ON episodes_pkey;


--
-- Name: genres_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY genres
    ADD CONSTRAINT genres_pkey PRIMARY KEY (id);


--
-- Name: library_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY library
    ADD CONSTRAINT library_pkey PRIMARY KEY (id);


--
-- Name: presets_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY presets
    ADD CONSTRAINT presets_pkey PRIMARY KEY (id);

ALTER TABLE presets CLUSTER ON presets_pkey;


--
-- Name: queue_hostname_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY queue
    ADD CONSTRAINT queue_hostname_key UNIQUE (hostname, episode_id);


--
-- Name: queue_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY queue
    ADD CONSTRAINT queue_pkey PRIMARY KEY (id);


--
-- Name: series_alt_titles_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY series_alt_titles
    ADD CONSTRAINT series_alt_titles_pkey PRIMARY KEY (id);


--
-- Name: series_collection_id_title_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY series
    ADD CONSTRAINT series_collection_id_title_key UNIQUE (collection_id, title);


--
-- Name: series_dvds_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY series_dvds
    ADD CONSTRAINT series_dvds_pkey PRIMARY KEY (id);


--
-- Name: series_library_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY series_library
    ADD CONSTRAINT series_library_pkey PRIMARY KEY (id);


--
-- Name: series_library_series_id_library_id_key; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY series_library
    ADD CONSTRAINT series_library_series_id_library_id_key UNIQUE (series_id, library_id);


--
-- Name: series_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY series
    ADD CONSTRAINT series_pkey PRIMARY KEY (id);


--
-- Name: series_presets_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY series_presets
    ADD CONSTRAINT series_presets_pkey PRIMARY KEY (id);

ALTER TABLE series_presets CLUSTER ON series_presets_pkey;


--
-- Name: specs_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY specs
    ADD CONSTRAINT specs_pkey PRIMARY KEY (id);

ALTER TABLE specs CLUSTER ON specs_pkey;


--
-- Name: subp_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY subp
    ADD CONSTRAINT subp_pkey PRIMARY KEY (id);

ALTER TABLE subp CLUSTER ON subp_pkey;


--
-- Name: tags_dvds_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY tags_dvds
    ADD CONSTRAINT tags_dvds_pkey PRIMARY KEY (id);


--
-- Name: tags_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY tags
    ADD CONSTRAINT tags_pkey PRIMARY KEY (id);


--
-- Name: tags_tracks_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY tags_tracks
    ADD CONSTRAINT tags_tracks_pkey PRIMARY KEY (id);


--
-- Name: tracks_pkey; Type: CONSTRAINT; Schema: public; Owner: -; Tablespace: 
--

ALTER TABLE ONLY tracks
    ADD CONSTRAINT tracks_pkey PRIMARY KEY (id);

ALTER TABLE tracks CLUSTER ON tracks_pkey;


--
-- Name: audio_track_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY audio
    ADD CONSTRAINT audio_track_id_fkey FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE;


--
-- Name: cells_track_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY cells
    ADD CONSTRAINT cells_track_id_fkey FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE;


--
-- Name: chapters_track_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY chapters
    ADD CONSTRAINT chapters_track_id_fkey FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE;


--
-- Name: collection_sets_collection_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY collection_sets
    ADD CONSTRAINT collection_sets_collection_id_fkey FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE;


--
-- Name: episodes_track_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY episodes
    ADD CONSTRAINT episodes_track_id_fkey FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE;


--
-- Name: fkey_queue; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY queue
    ADD CONSTRAINT fkey_queue FOREIGN KEY (episode_id) REFERENCES episodes(id) ON DELETE CASCADE;


--
-- Name: series_dvds_dvd_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY series_dvds
    ADD CONSTRAINT series_dvds_dvd_id_fkey FOREIGN KEY (dvd_id) REFERENCES dvds(id) ON DELETE CASCADE;


--
-- Name: series_dvds_series_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY series_dvds
    ADD CONSTRAINT series_dvds_series_id_fkey FOREIGN KEY (series_id) REFERENCES series(id) ON DELETE CASCADE;


--
-- Name: series_library_library_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY series_library
    ADD CONSTRAINT series_library_library_id_fkey FOREIGN KEY (library_id) REFERENCES library(id) ON DELETE CASCADE;


--
-- Name: series_library_series_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY series_library
    ADD CONSTRAINT series_library_series_id_fkey FOREIGN KEY (series_id) REFERENCES series(id) ON DELETE CASCADE;


--
-- Name: series_presets_series_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY series_presets
    ADD CONSTRAINT series_presets_series_id_fkey FOREIGN KEY (series_id) REFERENCES series(id) ON DELETE CASCADE;


--
-- Name: subp_track_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY subp
    ADD CONSTRAINT subp_track_id_fkey FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE;


--
-- Name: tags_tracks_tag_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY tags_tracks
    ADD CONSTRAINT tags_tracks_tag_id_fkey FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE;


--
-- Name: tags_tracks_track_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY tags_tracks
    ADD CONSTRAINT tags_tracks_track_id_fkey FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE;


--
-- Name: tracks_dvd_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY tracks
    ADD CONSTRAINT tracks_dvd_id_fkey FOREIGN KEY (dvd_id) REFERENCES dvds(id) ON DELETE CASCADE;


--
-- Name: public; Type: ACL; Schema: -; Owner: -
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--

