FROM nikolaik/python-nodejs:python3.12-nodejs18
ENV PYTHONIOENCODING utf-8

COPY /src /code/src/
COPY /tests /code/tests/
COPY /scripts /code/scripts/
COPY requirements.txt /code/requirements.txt
COPY flake8.cfg /code/flake8.cfg
COPY deploy.sh /code/deploy.sh

# install gcc to be able to build packages - e.g. required by regex, dateparser, also required for pandas
RUN apt-get update && apt-get install -y build-essential curl

# Install curlconverter using npm
RUN npm install --global curlconverter


RUN pip install flake8

RUN pip install -r /code/requirements.txt



WORKDIR /code/


CMD ["python", "-u", "/code/src/component.py"]
