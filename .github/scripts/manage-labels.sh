#!/bin/bash


### Managing labels
echo "Current branch is ${GITHUB_HEAD_REF}"
echo "Current application is ${DISTRIBUTION_APP}"
echo "Retrieving labels..."
echo $LABELS > labels.json

echo "Checking prestabulle label definition..."
if [ `jq -r '.[] |.name' labels.json |grep prestabulle |wc -l` -gt 1 ]
then
  echo "More than 1 prestabulle defined, this cannot be!"
  echo "Crash exiting the script!"
  echo ""
  exit -1
elif [ `jq -r '.[] |.name' labels.json |grep prestabulle |wc -l` -eq 1 ]
then
  BULLE=`cat labels.json | jq -r '.[]|.name'|grep prestabulle`
  echo "Prestabulle $BULLE has been defined, let's continue!"
fi

### Checking if label is already used or not
echo ""
echo "Checking if $BULLE is already being used..."

if [ `gsutil ls gs://$BUCKET_PRESTABULLE |grep $DISTRIBUTION_APP |wc -l` -eq 1 ]
then
  echo "Current application $DISTRIBUTION_APP has already been deployed, let's keep checking..."
  
  if [ `gsutil ls gs://$BUCKET_PRESTABULLE/$DISTRIBUTION_APP/|grep $BULLE |wc -l` -eq 1 ]
  then
    echo "Current $BULLE prestabulle has already been defined as well, let's keep checking..."

    gsutil -q cp gs://$BUCKET_PRESTABULLE/$DISTRIBUTION_APP/$BULLE .

    if [ ` grep ${GITHUB_HEAD_REF} $BULLE|wc -l` -eq 1 ]
    then
      echo "Current prestabulle is defined for current branch, let's continue!"
    else
      echo "Current prestabulle is already defined for ANOTHER branch!"
      echo "Please select ANOTHER available prestabulle :)"
      echo "FYI, here are the currently USED prestabulles:"

      for i in `gsutil ls gs://$BUCKET_PRESTABULLE/$DISTRIBUTION_APP/ |awk -F'/' '{ print $NF}'`
      do
        echo $i
      done 

      exit -1

    fi
  else
    echo "Current $BULLE is not defined for $DISTRIBUTION_APP."
    echo "Don't worry, we will manage this for you!"
  fi

  echo "Let's update $BULLE!"
  echo ${GITHUB_HEAD_REF} > $BULLE
  gsutil -q cp $BULLE  gs://$BUCKET_PRESTABULLE/$DISTRIBUTION_APP/

else

  echo "Let's create $BULLE!"
  echo ${GITHUB_HEAD_REF} > $BULLE
  gsutil -q cp $BULLE  gs://$BUCKET_PRESTABULLE/$DISTRIBUTION_APP/

fi

rm labels.json
rm $BULLE
