
create schema if not exists reconciliation;

---------------------Registry

create table reconciliation.registry_config
(
    id                 bigint       not null
        primary key,
    provider           varchar(255) not null,
    provider_wallet_id integer      not null,
    slug               varchar(255) not null,
    is_active          boolean,
    created_at         timestamp(0) not null,
    description        varchar(255) default NULL::character varying,
    utc                varchar(255) default 'UTC-3'::character varying
);

create sequence reconciliation.registry_config_id_seq;


create table reconciliation.registry_command
(
    id                 integer      not null
        primary key,
    registry_config_id integer      not null,
    status             varchar(255) not null,
    total_amount       bigint       not null,
    total_count        integer      not null,
    description        varchar(255) default NULL::character varying,
    created_at         timestamp(0) not null,
    on_date            timestamp(0) not null
);

create sequence reconciliation.registry_command_id_seq;

create table reconciliation.registry_transactions
(
    id               integer not null
        primary key,
    command_id       integer not null,
    type             varchar(255) default NULL::character varying,
    processing_id    varchar(255) default NULL::character varying,
    provider_id      varchar(255) default NULL::character varying,
    amount           integer,
    fee              integer,
    cardnumber       varchar(255) default NULL::character varying,
    approve_code     varchar(50)  default NULL::character varying,
    transaction_date timestamp(0) default NULL::timestamp without time zone
);

create sequence reconciliation.registry_transactions_id_seq;

create table reconciliation.registry_error_log
(
    id            integer      not null
        primary key,
    command_id    integer      not null,
    type          varchar(255) not null,
    description   varchar(255) default NULL::character varying,
    created_at    timestamp(0) not null,
    processing_id varchar(255) default NULL::character varying,
    provider_id   varchar(255) default NULL::character varying,
    started       timestamp(0) not null,
    status        varchar(50)  not null
);

create sequence reconciliation.registry_error_log_id_seq;


--------------Balance

create table reconciliation.balance_config
(
    id           bigint       not null
        primary key,
    partner_id   integer      not null,
    partner_name varchar(255) not null
        unique,
    wallet_id    integer      not null,
    is_active    boolean,
    description  varchar(255) default NULL::character varying,
    type         varchar,
    provider     varchar(255) default NULL::character varying
);

create sequence reconciliation.balance_config_id_seq;


create table reconciliation.balance_process
(
    id                integer      not null
        primary key,
    balance_config_id integer      not null
        constraint fk_e3a19cf4960109df
            references reconciliation.balance_config,
    total_amount      bigint       not null,
    counter_in_was    bigint       not null,
    counter_in_be     bigint       not null,
    status            varchar(255) not null,
    created_at        timestamp(0) not null,
    on_date           timestamp(0) not null
);

create sequence reconciliation.balance_process_id_seq;


create table reconciliation.balance_error_log
(
    id          integer      not null
        primary key,
    process_id  integer      not null,
    created_at  timestamp(0) not null,
    description varchar(255) default NULL::character varying
);

create sequence reconciliation.balance_error_log_id_seq;


create table reconciliation.balance_partners
(
    id                     integer      not null
        primary key,
    partner_id             integer,
    name                   varchar(255) not null,
    counter_in             bigint,
    credit                 bigint,
    counter_out_old        bigint,
    counter_out_calculated bigint,
    balance                bigint,
    created_at             timestamp(0) not null,
    type                   varchar
);

create sequence reconciliation.balance_partners_id_seq;

--------------Email

create table reconciliation.email_config
(
    id          bigint       not null primary key,
    partner_id  json         not null,
    email_to    json,
    email_cc    json,
    type        json,
    slug        varchar(255) not null unique,
    is_active   boolean,
    created_at  timestamp(0) not null,
    description varchar(255) default NULL::character varying,
    format varchar(50) default NULL::character varying,
    utc varchar(255) default 'UTC-3'::character varying,
    provider json,
    terminal json,
    is_total_amount_checked boolean default false,
    is_refund_checked boolean default false,
    registry_type varchar(50) default NULL::character varying
);

create sequence reconciliation.email_config_id_seq;


create table reconciliation.email_process
(
    id                integer      not null primary key,
    email_config_id   integer      not null constraint fk_e3a19cf4960119df
        references reconciliation.email_config,
    total_amount      json,
    balance           json,
    regs              json,
    status            varchar(255) not null,
    created_at        timestamp(0) not null,
    on_date           timestamp(0) not null,
    description       varchar(255),
    count             json
);

create sequence reconciliation.email_process_id_seq;


create table reconciliation.email_error_log
(
    id          integer      not null
        primary key,
    process_id  integer      not null,
    created_at  timestamp(0) not null,
    description varchar(255) default NULL::character varying
);
create sequence reconciliation.email_error_log_id_seq;


---------------Collation

create table reconciliation.collation_issues
(
    id            integer not null
        primary key,
    command_id    integer not null,
    type          varchar(255) not null,
    description   varchar(255) default NULL::character varying,
    created_at    timestamp(0) not null,
    processing_id varchar(255) default NULL::character varying,
    provider_id   varchar(255) default NULL::character varying,
    started       timestamp(6) default NULL::timestamp without time zone,
    status        varchar(50)  not null
);

create sequence reconciliation.collation_issues_id_seq;

create table reconciliation.collation_issues_log
(
    id            integer not null
        primary key,
    command_id    integer not null,
    created_at    timestamp(0) not null,
    processing_id varchar(255) default NULL::character varying,
    provider_id   varchar(255) default NULL::character varying,
    description   varchar(255) default NULL::character varying
);

create sequence reconciliation.collation_issues_log_id_seq;

create table reconciliation.collations
(
    id            integer not null
        primary key,
    processing_id varchar(255) not null,
    command_id    integer not null,
    created_at    timestamp(0) not null
);

create sequence reconciliation.collations_id_seq;


---------------User

create table reconciliation."user"
(
    id       integer not null
        primary key,
    email    varchar(180) not null
        unique,
    roles    json not null,
    password varchar(255) not null,
    root     boolean not null
);

create sequence reconciliation.user_id_seq;


---For test
create table partners
(
    id                        bigserial
        primary key,
    login                     varchar(255),
    name                      varchar(255),
    pass                      varchar(255),
    encrypted_password        varchar(255),
    is_active                 boolean default true,
    inserted_at               timestamp(0) not null,
    updated_at                timestamp(0) not null,
    need_confirm              boolean default false,
    params                    jsonb   default '{}'::jsonb,
    account_generation_schema varchar(255)
);



create table terminals
(
    id                   bigserial
        primary key,
    env                  varchar(255),
    type                 varchar(255),
    provider             varchar(255),
    provider_params      jsonb,
    priority             integer,
    is_active            boolean      default true,
    work_schedule        varchar(255),
    partner_id           bigint
        references partners,
    inserted_at          timestamp(0) not null,
    updated_at           timestamp(0) not null,
    min_amount           integer,
    max_amount           integer,
    ruleset              varchar(255),
    fees                 jsonb,
    route                jsonb        default '[]'::jsonb,
    wallet_type          varchar(255) default 'internal'::character varying,
    wallet_name          varchar(255) default 'general'::character varying,
    currency             varchar(255) default 'RUB'::character varying,
    change_description   boolean      default true,
    default_description  varchar(255) default ''::character varying,
    active_from          timestamp(0) default NULL::timestamp without time zone,
    active_to            timestamp(0) default NULL::timestamp without time zone,
    inspector_params     jsonb,
    merchant_adapter     varchar(255),
    merchant_params      jsonb,
    traffic_level        integer,
    provider_wallet_name varchar(255),
    weight               integer
);



create table wallets
(
    id          bigserial
        primary key,
    credit      bigint,
    counter_in  bigint,
    counter_out bigint,
    type        varchar(255),
    partner_id  bigint
        references partners,
    inserted_at timestamp(0) not null,
    updated_at  timestamp(0) not null,
    env         varchar(255),
    currency    varchar(255) default 'RUB'::character varying,
    name        varchar(255) default 'general'::character varying,
    provider    varchar(255)
);




create table provider_wallets
(
    id          bigserial
        primary key,
    credit      bigint,
    counter_in  bigint,
    counter_out bigint,
    currency    varchar(255) default 'RUB'::character varying,
    name        varchar(255) default 'general'::character varying,
    type        varchar(255),
    env         varchar(255),
    provider    varchar(255),
    inserted_at timestamp(0) not null,
    updated_at  timestamp(0) not null
);




create table txs
(
    id                        uuid                                           not null
        primary key,
    money_storage             jsonb,
    widget_url                varchar(255),
    cardnumber                varchar(255),
    phone                     varchar(255),
    recipient                 varchar(255),
    fiscalization_data        jsonb,
    amount                    integer,
    type                      varchar(255),
    description               text,
    started                   timestamp(0),
    finished                  timestamp(0),
    outertxid                 varchar(255),
    txid                      varchar(255),
    provider                  varchar(255),
    status                    varchar(255),
    partner_id                bigint
        references partners,
    inserted_at               timestamp(0)                                   not null,
    updated_at                timestamp(0)                                   not null,
    auth_params               jsonb,
    currency                  varchar(255),
    current_priority          integer,
    current_terminal          bigint
        references terminals,
    inspector_stage           jsonb,
    wallet_id                 bigint
        references wallets,
    error_description         varchar(255),
    fees                      jsonb,
    attempts                  jsonb        default '[]'::jsonb,
    env                       varchar(255) default 'prod'::character varying not null,
    money_storage_meta        jsonb        default '{}'::jsonb               not null,
    amount_with_fee           integer,
    merchant_redirect_url     varchar(255),
    numeric_id                varchar(255),
    merchant_fail_url         varchar(255),
    rrn                       varchar(255),
    route                     varchar(255),
    current_terminal_endpoint varchar(255),
    original_description      text,
    provider_initialized_at   timestamp(0) default NULL::timestamp without time zone,
    pivot_timepoint           timestamp(0) default NULL::timestamp without time zone,
    currency_dest             varchar(255) default 'RUB'::character varying,
    currency_rate             varchar(255) default '1'::character varying,
    initial_params            jsonb        default '{}'::jsonb,
    secured_box               text,
    target_bank_id            varchar(255),
    availble_destinations     jsonb[],
    money_storage_merchant    jsonb,
    traffic_level             integer,
    merchanttxid              varchar(255),
    provider_meta             jsonb        default '{}'::jsonb,
    merchant_meta             jsonb        default '{}'::jsonb,
    provider_wallet_id        bigint
        references provider_wallets,
    executed_on               varchar(255) default NULL::character varying,
    execution_finished        boolean,
    recovery_point            jsonb,
    update_entity_counter     integer,
    auth_started              timestamp(0) default NULL::timestamp without time zone,
    amount_dest               integer,
    provider_amount           integer,
    merchant_amount           integer,
    provider_wallet_amount    integer,
    merchant_wallet_amount    integer,
    link_id                   varchar(255),
    revenue                   integer      default 0
);
