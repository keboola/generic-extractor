FROM nikolaik/python-nodejs:python3.12-nodejs18

RUN apt-get update && apt-get install -y curl

RUN npm install --global curlconverter

RUN pip install flake8

COPY requirements.txt /code/requirements.txt
RUN pip install -r /code/requirements.txt

COPY flake8.cfg /code/flake8.cfg

COPY /src /code/src/
COPY /tests /code/tests/

WORKDIR /code/

CMD ["python", "-u", "/code/src/component.py"]
