/**
  *  SD bee Access database initialisation
  */

/**
* Users
*/
DROP TABLE IF EXISTS `Users`;
CREATE TABLE `Users` (
  name text NOT NULL,               /* User's login */
  password text NOT NULL,           /* User's password */
  'doc-storage' text NOT NULL,      /*Name of storage to use for documents */
  'resource-storage' text,          /* Name of storage to use for private (not built-in) resources */
  'service-gateway' text,           /* Name of service gateway */
  'service-username' text,          /* Username of service account */
  'service-password' text,          /* Password of account on service gateway */
  'top-doc-dir' text,               /* Top directory in doc storage */
  home text,                        /* Collection or task to display for home */
  prefix text,                      /* Default prefix to add to filenames in doc storage */
  key text                          /* Default key to use for crypting files in doc storage */
  
);
INSERT INTO `Users` VALUES
    ('demo', 'CRYPT(demo)', 'private-storage', '', 'https://www.sd-bee.com/webdesk/', 'demo', 'demo', '', 'A0012345678920001_trialhome', 'ymbNpnZm8', '');

/**
* Members = remember me cookies
*/
DROP TABLE IF EXISTS `Members`;
CREATE TABLE `Members` (
  token text NOT NULL,              /* Value of cookie */
  ip text DEFAULT NULL,             /* IP address associated with token */
  userId int(11) NOT NULL,          /* Id of associated user */
  validDate int(11) DEFAULT NULL    /* Validity date */
);


DROP TABLE IF EXISTS `Docs`;
CREATE TABLE `Docs` (
  name text NOT NULL,
  label text NOT NULL,
  type int(5),
  model text DEFAULT NULL,
  description text DEFAULT NULL,
  params text DEFAULT NULL,
  prefix text DEFAULT NULL,
  created int(11) DEFAULT NULL,
  updated int(11) DEFAULT NULL,
  state text DEFAULT NULL,
  progress int(5) 
);
INSERT INTO `Docs` VALUES
    ('A0012345678920001_trialhome', 'trialhome', 1, 'none', 'trial dir', '', '', 0, 0, '', 0),
    ('A0000002NHSEB0000M_Repageaf', 'trial doc', 2, 'ASS000000000301_System', 'test doc derived from repage test', '', '', 0, 0, '', 0);

/* UserLinks : userId, isUser, targetId, access, */
DROP TABLE IF EXISTS `UserLinks`;
CREATE TABLE `UserLinks` (
  userId int(11) NOT NULL,
  isUser tinyint(1) DEFAULT NULL,
  targetId int(11) NOT NULL,
  access int(11) DEFAULT NULL,
  PRIMARY KEY( userId, isUser, targetId)
) WITHOUT ROWID;
INSERT INTO `UserLinks` VALUES ( 1, 0, 1, 7);

/*  CollectionLinks : collectionId, isDoc, targetId, access*/
DROP TABLE IF EXISTS `CollectionLinks`;
CREATE TABLE `CollectionLinks` (
  collectionId int(11) NOT NULL,
  isDoc tinyint(1) DEFAULT NULL,
  targetId int(11) NOT NULL,
  access int(11) DEFAULT NULL,
  PRIMARY KEY( collectionId, isDoc, targetId)
) WITHOUT ROWID;
INSERT INTO CollectionLinks VALUES( 1, 1, 2, 7);

/*  LoadedFiles : date, filename, report*/
DROP TABLE IF EXISTS 'LoadedFiles';
CREATE TABLE 'LoadedFiles' (
  date int(11) NOT NULL,
  file text NOT NULL,
  report text 
);
