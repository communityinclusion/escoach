CREATE TABLE `surveycampaign_campaigns`  (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `surveyid` int(11) NOT NULL,
  `campaignid` int(11) NOT NULL,
  `senddate` timestamp,
  `text1` int(1) NOT NULL,
  `text2` int(1) NOT NULL,
  `text3` int(1) NOT NULL,
  PRIMARY KEY (`ID`) USING BTREE,
  INDEX `surveycampaign`(`surveyid`, `campaignid`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_unicode_ci ROW_FORMAT = Compact