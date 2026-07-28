SELECT 
  'EMAIL duplicates' as check_type,
  COUNT(*) as cnt
FROM (
  SELECT rc.value_normalized_key
  FROM app.reader_contacts rc
  WHERE rc.contact_type = 'EMAIL'
  GROUP BY rc.value_normalized_key
  HAVING COUNT(*) > 1
) dupes;

SELECT 
  'AD_LOGIN contacts' as contact_check,
  COUNT(*) as cnt
FROM app.reader_contacts rc
WHERE rc.contact_type = 'AD_LOGIN';

SELECT 
  'Total readers' as stat,
  COUNT(*) as cnt
FROM app.readers;

SELECT 
  'Total EMAIL contacts' as stat,
  COUNT(*) as cnt
FROM app.reader_contacts rc
WHERE rc.contact_type = 'EMAIL';

SELECT 
  'Readers with EMAIL contact' as stat,
  COUNT(DISTINCT rc.reader_id) as cnt
FROM app.reader_contacts rc
WHERE rc.contact_type = 'EMAIL';
