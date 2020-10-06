#
# Table structure extend for table 'tx_sitefactory_domain_model_save'
#
CREATE TABLE tx_sitefactory_domain_model_save (
	uid                     int(11) NOT NULL auto_increment,
	pid                     int(11) NOT NULL,
    tstamp                  int(11) DEFAULT '0' NOT NULL,
    crdate                  int(11) DEFAULT '0' NOT NULL,
    cruser_id               int(11) DEFAULT '0' NOT NULL,
    sorting                 int(11) DEFAULT '0' NOT NULL,
    deleted                 tinyint(4) DEFAULT '0' NOT NULL,
    hidden                  tinyint(4) DEFAULT '0' NOT NULL,

	root_page_uid           int(11) NOT NULL,
	date                    int(11) DEFAULT 0,
	configuration           TEXT DEFAULT '',

    PRIMARY KEY (uid),
    KEY parent (pid)

);

#
# Table structure extend for table 'sys_template'
#
CREATE TABLE sys_template (
	site_factory_template tinyint(1) unsigned DEFAULT '0' NOT NULL,
);

#
# Table structure extend for table 'pages'
#
CREATE TABLE pages (
	site_factory_association_parent int(11) DEFAULT '0' NOT NULL,
);
