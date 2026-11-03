"""Author: Salman | Version: 1.2.0
Description: Local HTTP return redirect and rejection tests.
Copyright (C) 2026 Salman. SPDX-License-Identifier: GPL-2.0-or-later"""
"""Local HTTP tests of production return handler with mocked WC/provider dependencies."""
from pathlib import Path
import subprocess,socket,time,urllib.request,urllib.error
root=Path(__file__).resolve().parent
sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close()
proc=subprocess.Popen(['php','-S',f'127.0.0.1:{port}',str(root/'return-http-router.php')],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*args):return None
opener=urllib.request.build_opener(NoRedirect)
try:
 for _ in range(40):
  try:
   with socket.create_connection(('127.0.0.1',port),timeout=.2):break
  except OSError:time.sleep(.05)
 count=0
 for case in ['paid','verified','pending']:
  for key in ['wc_test&status=completed','wc_test?status=completed']:
   url=f'http://127.0.0.1:{port}/?case={case}&ztp_order=123&key={key}'
   try:response=opener.open(url)
   except urllib.error.HTTPError as e:response=e
   assert response.code==302,(case,response.code,response.read())
   destination='order-pay' if case=='pending' else 'order-received'
   assert destination in response.headers.get('Location',''),response.headers
   count+=1
 for key in ['wrong?status=completed','wc_test_extra?status=completed']:
  try:response=opener.open(f'http://127.0.0.1:{port}/?ztp_order=123&key={key}')
  except urllib.error.HTTPError as e:response=e
  assert response.code==403,response.code
  count+=1
 print(f'PASS {count} local HTTP redirect/rejection checks')
finally:
 proc.terminate();proc.wait(timeout=5)
