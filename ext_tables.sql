CREATE TABLE tx_pxecomposerreport_domain_model_run (
    collector       varchar(16)  DEFAULT '' NOT NULL,
    status          varchar(16)  DEFAULT 'ok' NOT NULL,
    total_outdated  int(11)      DEFAULT 0 NOT NULL,
    major           int(11)      DEFAULT 0 NOT NULL,
    minor           int(11)      DEFAULT 0 NOT NULL,
    patch           int(11)      DEFAULT 0 NOT NULL,
    security_count  int(11)      DEFAULT 0 NOT NULL,
    typo3_count     int(11)      DEFAULT 0 NOT NULL,
    report_json     mediumtext,
    ai_summary      text,
    error_log       text,
    triggered_by    varchar(16)  DEFAULT '' NOT NULL,

    KEY crdate_idx (crdate)
);
